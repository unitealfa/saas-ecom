<?php

use App\Enums\VerificationStatusEnum;
use App\Models\Central\Activity;
use App\Models\Central\Country;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Notifications\Central\AccountInvitation;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
    Notification::fake();
    Queue::fake();
});

function editableCentralProfile(User $user): array
{
    return [
        'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email,
        'phone' => $user->phone, 'country_uuid' => $user->country()->firstOrFail()->uuid,
    ];
}

test('an admin created account sends its invitation immediately without a queue worker', function (): void {
    Notification::swap(new ChannelManager(app()));
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    $this->actingAs($this->admin, 'central')->post(route('central.users.store'), [
        'last_name' => 'Invited', 'email' => 'immediate@example.test', 'account_kind' => 'merchant',
        'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
    ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Compte créé pour Invited. L’invitation a été envoyée.');
    expect($transport->messages())->toHaveCount(1);
    $message = $transport->messages()->first()->getOriginalMessage();
    expect($message->getTo()[0]->getAddress())->toBe('immediate@example.test');
    expect($message->getHtmlBody())->toContain('Choisir mon mot de passe');
    expect(User::where('email', 'immediate@example.test')->exists())->toBeTrue();
    Queue::assertNothingPushed();
});

test('SMTP failure keeps the new account and shows a warning without exposing the private error', function (): void {
    Notification::swap(new ChannelManager(app()));
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->andThrow(new TransportException('private-smtp-secret'));
    Mail::mailer()->setSymfonyTransport($transport);
    Log::spy();
    $this->actingAs($this->admin, 'central')->post(route('central.users.store'), [
        'last_name' => 'Retained', 'email' => 'retained@example.test', 'account_kind' => 'merchant',
        'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
    ])->assertRedirect(route('central.users.index'))
        ->assertSessionHas('warning', fn (string $message): bool => str_contains($message, 'n’a pas pu être envoyée')
            && ! str_contains($message, 'private-smtp-secret'));
    $user = User::where('email', 'retained@example.test')->firstOrFail();
    Log::shouldHaveReceived('warning')->once()->with('Central account email delivery failed.', [
        'user_uuid' => $user->uuid, 'exception_class' => TransportException::class,
    ]);
    expect(Activity::where('event', 'account_invited')->count())->toBe(1);
    Queue::assertNothingPushed();
});

test('shop actions belong only to merchants and staff cannot bypass the hidden buttons by URL', function (): void {
    $merchant = User::factory()->create();
    $staff = User::factory()->create();
    $staff->assignRole(Role::findByName('user-reader'));
    $staff->roles()->updateExistingPivot(Role::findByName('user-reader')->id, ['assigned_at' => now()->subDays(10000)]);
    expect(Gate::forUser($this->admin)->allows('create', [Tenant::class, $staff]))->toBeFalse();
    $this->actingAs($this->admin, 'central')->get(route('central.users.index'))->assertOk()
        ->assertSee(route('central.users.tenants.create', $merchant), escape: false)
        ->assertDontSee(route('central.users.tenants.create', $staff), escape: false)
        ->assertDontSee(route('central.users.tenants.create', $this->admin), escape: false)
        ->assertSee(route('central.users.show', $merchant), escape: false)
        ->assertSee(route('central.users.edit', $merchant), escape: false);
    $this->get(route('central.users.tenants.create', $staff))->assertForbidden();
    $this->post(route('central.users.tenants.store', $staff), [
        'shop_name' => 'Forbidden', 'slug' => 'forbidden', 'creation_key' => (string) Str::uuid(),
    ])->assertForbidden();
    expect(Tenant::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('readers can inspect escaped account information but cannot edit or resend invitations', function (): void {
    $reader = User::factory()->create();
    $reader->assignRole(Role::findByName('user-reader'));
    $target = User::factory()->create(['first_name' => '<script>alert(1)</script>', 'phone' => '+213555123456']);
    $this->actingAs($reader, 'central')->get(route('central.users.show', $target))->assertOk()
        ->assertSee($target->uuid)->assertSee($target->phone)
        ->assertSee('&lt;script&gt;', escape: false)->assertDontSee('<script>alert(1)</script>', escape: false)
        ->assertDontSee($target->password, escape: false)
        ->assertDontSee(route('central.users.edit', $target), escape: false);
    $this->get(route('central.users.edit', $target))->assertForbidden();
    $this->put(route('central.users.update', $target), editableCentralProfile($target))->assertForbidden();
    $this->post(route('central.users.invitation', $target))->assertForbidden();
    Notification::assertNothingSent();
});

test('merchant edits preserve the real identifiers password and roles and invalidate changed contact proofs', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole(Role::findByName('user-manager'));
    $target = User::factory()->create(['phone' => '+213555123456']);
    $target->forceFill([
        'phone_verified_at' => now(), 'whatsapp_verified_at' => now(),
        'legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => $this->admin->id,
    ])->save();
    $password = $target->password;
    $emailVerifiedAt = $target->email_verified_at;
    $this->actingAs($manager, 'central')->get(route('central.users.edit', $target))->assertOk();
    $this->put(route('central.users.update', $target), [
        ...editableCentralProfile($target), 'first_name' => ' Nour ', 'last_name' => ' Newname ', 'phone' => '+213555999999',
    ])->assertSessionHasNoErrors()->assertRedirect(route('central.users.show', $target));
    $updated = App\Models\Central\User::findOrFail($target->id);
    expect($updated->uuid)->toBe($target->uuid);
    expect($updated->id)->toBe($target->id);
    expect($updated->country_id)->toBe($target->country_id);
    expect($updated->password)->toBe($password);
    expect($updated->first_name)->toBe('Nour');
    expect($updated->last_name)->toBe('Newname');
    expect($updated->email_verified_at)->toEqual($emailVerifiedAt);
    expect($updated->phone_verified_at)->toBeNull();
    expect($updated->whatsapp_verified_at)->toBeNull();
    expect($updated->legal_profile_version)->toBe(2);
    expect($updated->legal_verification_status)->toBe(VerificationStatusEnum::PENDING);
    expect($updated->roles()->exists())->toBeFalse();
    expect(Activity::where('event', 'central_profile_updated')->count())->toBe(1);
    Notification::assertNothingSent();
});

test('staff edits require the staff permission and cannot take over a more privileged account', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole(Role::findByName('user-manager'));
    $reader = User::factory()->create();
    $reader->assignRole(Role::findByName('user-reader'));
    $delegator = User::factory()->create();
    $delegator->assignRole(Role::createWithPermissions('limited-staff-editor', [
        'saas.users.view' => 9999, 'saas.staff.invite' => 9999,
    ]));
    expect(Gate::forUser($manager)->allows('update', $reader))->toBeFalse();
    expect(Gate::forUser($delegator)->allows('update', $reader))->toBeTrue();
    expect(Gate::forUser($delegator)->allows('update', $manager))->toBeFalse();
    expect(Gate::forUser($delegator)->allows('update', $this->admin))->toBeFalse();
    $this->actingAs($manager, 'central')->get(route('central.users.edit', $reader))->assertForbidden();
    $this->actingAs($delegator, 'central')->put(route('central.users.update', $reader), [
        ...editableCentralProfile($reader), 'last_name' => 'Updatedreader',
    ])->assertSessionHasNoErrors();
    expect($reader->fresh()->last_name)->toBe('Updatedreader');
    expect($reader->hasRole('user-reader'))->toBeTrue();
    $this->put(route('central.users.update', $this->admin), [
        ...editableCentralProfile($this->admin), 'email' => 'takeover@example.test',
    ])->assertForbidden();
    expect($this->admin->fresh()->email)->toBe($this->admin->email);
});

test('changing the email removes the old password token and requires verification of the new destination', function (): void {
    $target = User::factory()->create();
    $password = $target->password;
    Password::broker('central')->createToken($target);
    $this->actingAs($this->admin, 'central')->put(route('central.users.update', $target), [
        ...editableCentralProfile($target), 'email' => ' NewAddress@Example.test ',
    ])->assertSessionHasNoErrors();
    $updated = $target->fresh();
    expect($updated->email)->toBe('newaddress@example.test');
    expect($updated->email_verified_at)->toBeNull();
    expect($updated->password)->toBe($password);
    expect(DB::table('password_reset_tokens')->where('email', $target->email)->exists())->toBeFalse();
    Notification::assertSentTo(App\Models\Central\User::findOrFail($target->id), VerifyEmail::class);
});

test('editing an unverified account sends a fresh invitation to its new address and keeps incomplete legal status', function (): void {
    $target = User::factory()->unverified()->create();
    $target->forceFill(['legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::INCOMPLETE])->save();
    Password::broker('central')->createToken($target);
    $this->actingAs($this->admin, 'central')->put(route('central.users.update', $target), [
        ...editableCentralProfile($target), 'email' => 'new-invited@example.test',
    ])->assertSessionHasNoErrors();
    $updated = $target->fresh();
    expect($updated->legal_verification_status)->toBe(VerificationStatusEnum::INCOMPLETE);
    expect($updated->legal_profile_version)->toBe(2);
    expect(DB::table('password_reset_tokens')->where('email', $target->email)->exists())->toBeFalse();
    Notification::assertSentTo(App\Models\Central\User::findOrFail($target->id), AccountInvitation::class);
});

test('edit validation rejects duplicates malformed fields inactive countries and privilege injection', function (): void {
    $this->seed(CountrySeeder::class);
    $target = User::factory()->create();
    $other = User::factory()->create();
    $snapshot = $target->fresh()->getAttributes();
    $this->actingAs($this->admin, 'central')->put(route('central.users.update', $target), [
        ...editableCentralProfile($target), 'email' => $other->email,
    ])->assertSessionHasErrors('email');
    $this->put(route('central.users.update', $target), [
        ...editableCentralProfile($target), 'first_name' => ['invalid'], 'last_name' => ['invalid'],
        'email' => ['invalid'], 'phone' => ['invalid'],
    ])->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone']);
    $this->put(route('central.users.update', $target), [
        ...editableCentralProfile($target), 'country_uuid' => Country::where('code', 'FR')->firstOrFail()->uuid,
    ])->assertSessionHasErrors('country_uuid');
    $this->put(route('central.users.update', $target), [
        ...editableCentralProfile($target), 'password' => 'injected', 'status' => 3,
        'roles' => [Role::findByName('root')->uuid], 'is_super_admin' => true, 'email_verified_at' => now()->toDateTimeString(),
    ])->assertSessionHasErrors(['password', 'status', 'roles', 'is_super_admin', 'email_verified_at']);
    expect($target->fresh()->getAttributes())->toBe($snapshot);
    Notification::assertNothingSent();
});

test('account view and editing respect target restrictions and expired update permissions', function (): void {
    $this->freezeTime();
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('temporary-editor', ['saas.users.view' => 9999, 'saas.users.update' => 1]));
    $target = User::factory()->create();
    $this->actingAs($actor, 'central')->get(route('central.users.edit', $target))->assertOk();
    $this->travel(1)->days();
    $this->put(route('central.users.update', $target), editableCentralProfile($target))->assertForbidden();
    DB::table('admin_restrictions')->insert([
        'uuid' => (string) Str::uuid(), 'admin_id' => $actor->id,
        'permission_id' => DB::table('permissions')->where('name', 'saas.users.view')->value('id'),
        'target_user_id' => $target->id, 'effect' => 1, 'status' => 1, 'started_at' => now(), 'created_by_id' => $this->admin->id,
    ]);
    $this->get(route('central.users.show', $target))->assertForbidden();
});

test('an authorized invitation retry renews the token and verified accounts cannot be reinvited', function (): void {
    $target = User::factory()->unverified()->create();
    $oldToken = Password::broker('central')->createToken($target);
    $this->actingAs($this->admin, 'central')->get(route('central.users.show', $target))->assertOk()->assertSee('Renvoyer l’invitation');
    $this->post(route('central.users.invitation', $target))->assertSessionHasNoErrors()->assertSessionHas('status', 'L’invitation a été envoyée.');
    Notification::assertSentTo(App\Models\Central\User::findOrFail($target->id), AccountInvitation::class);
    expect(Password::broker('central')->tokenExists($target, $oldToken))->toBeFalse();
    expect(Activity::where('event', 'account_invitation_requested')->count())->toBe(1);
    $verified = User::factory()->create();
    $this->post(route('central.users.invitation', $verified))->assertSessionHasErrors('invitation');
});
