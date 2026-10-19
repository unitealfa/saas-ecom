<?php

use App\Models\Central\Country;
use App\Models\Central\Role;
use App\Models\Central\UserContact;
use App\Models\User;
use App\Notifications\Central\Auth\VerifyContactEmail;
use App\Services\Central\Users\UserContactService;
use App\Services\Central\Users\UserManagementService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([\Database\Seeders\Central\CountrySeeder::class, AuthorizationSeeder::class]);
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
    $this->owner = User::factory()->create(['phone' => '0779107446']);
});

test('primary contacts are registered automatically with real SQL ownership constraints', function (): void {
    $contacts = $this->owner->contacts()->get();
    expect($contacts)->toHaveCount(2);
    expect($contacts->every(fn ($contact) => $contact->is_primary && \Illuminate\Support\Str::isUuid($contact->uuid, version: 4)))->toBeTrue();
    expect(fn () => DB::table('user_contacts')->insert([
        'uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $this->admin->id,
        'kind' => 'phone', 'value' => $this->owner->phone, 'country_id' => $this->owner->phone_country_id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('SQL rejects a duplicate public UUID or a second primary contact for the same type', function (): void {
    $primary = $this->owner->contacts()->where('kind', 'email')->firstOrFail();
    $attributes = ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $this->owner->id,
        'kind' => 'email', 'value' => 'extra@example.test', 'is_primary' => true];
    expect(fn () => DB::table('user_contacts')->insert($attributes))->toThrow(UniqueConstraintViolationException::class);
    expect(fn () => DB::table('user_contacts')->insert([...$attributes, 'uuid' => $primary->uuid, 'is_primary' => false]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('an administrator adds secondary contacts and they reserve normalized values globally', function (): void {
    $this->actingAs($this->admin, 'central')->post(route('central.users.contacts.store', $this->owner), [
        'kind' => 'phone', 'value' => '0555 12 34 56', 'country_uuid' => $this->owner->country->uuid,
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('user_contacts', ['user_id' => $this->owner->id, 'value' => '+213555123456', 'is_primary' => false]);
    expect(fn () => User::factory()->create(['phone' => '+213555123456']))->toThrow(ValidationException::class);
    $service = app(UserContactService::class);
    $contact = $service->add($this->admin, $this->owner, 'email', ' SECONDARY@EXAMPLE.TEST ');
    expect($contact->value)->toBe('secondary@example.test')->and($contact->verified_at)->toBeNull();
    expect(fn () => User::factory()->create(['email' => 'secondary@example.test']))->toThrow(ValidationException::class);
    expect(fn () => $service->add($this->admin, $this->admin, 'email', $this->owner->email))->toThrow(ValidationException::class);
});

test('public and administrative creation reject the same existing normalized email and phone', function (): void {
    $public = ['last_name' => 'Nouveau', 'email' => 'new@example.test', 'phone' => '0779107446',
        'password' => 'password', 'password_confirmation' => 'password', 'country_uuid' => $this->owner->country->uuid];
    $this->postJson(route('register.store'), $public)->assertUnprocessable()->assertJsonValidationErrors('phone');
    $this->postJson(route('register.store'), [...$public, 'email' => strtoupper($this->owner->email), 'phone' => '0661123456'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $administrative = ['last_name' => 'Nouveau', 'email' => 'new@example.test', 'phone' => '+213779107446',
        'country_uuid' => $this->owner->country->uuid, 'account_kind' => 'merchant'];
    $this->actingAs($this->admin, 'central')->postJson(route('central.users.store'), $administrative)
        ->assertUnprocessable()->assertJsonValidationErrors('phone');
    $this->postJson(route('central.users.store'), [...$administrative, 'email' => $this->owner->email, 'phone' => '0661123456'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    expect(User::count())->toBe(2);
});

test('profile and administrative edits keep their own contacts but reject another owners phone', function (): void {
    $this->actingAs($this->admin, 'central');
    \Livewire\Livewire::test('pages::settings.profile')->call('updateProfileInformation')->assertHasNoErrors();
    \Livewire\Livewire::test('pages::settings.profile')->set('phone', '0779107446')->call('updateProfileInformation')->assertHasErrors('phone');
    $this->putJson(route('central.users.update', $this->admin), [
        'last_name' => $this->admin->last_name, 'first_name' => $this->admin->first_name,
        'email' => $this->admin->email, 'phone' => '+213779107446', 'country_uuid' => $this->admin->country->uuid,
    ])->assertUnprocessable()->assertJsonValidationErrors('phone');
    expect($this->admin->fresh()->phone)->toBeNull()->and($this->owner->fresh()->phone)->toBe('+213779107446');
});

test('SQL also prevents replacing a primary contact with another users secondary contact', function (): void {
    $contact = app(UserContactService::class)->add($this->admin, $this->owner, 'email', 'reserved@example.test');
    expect(fn () => DB::table('users')->where('id', $this->admin->id)->update(['email' => $contact->value]))
        ->toThrow(UniqueConstraintViolationException::class);
    expect($this->admin->fresh()->email)->not->toBe($contact->value);
});

test('the owner receives a signed verification and only their account can consume it', function (): void {
    $this->actingAs($this->admin, 'central')->post(route('central.users.contacts.store', $this->admin), [
        'kind' => 'email', 'value' => 'second@example.test',
    ])->assertSessionHasNoErrors();
    Notification::assertSentOnDemand(VerifyContactEmail::class, fn ($notification, $channels, $notifiable) =>
        $notifiable->routes['mail'] === 'second@example.test');
    $contact = $this->admin->contacts()->where('value', 'second@example.test')->firstOrFail();
    $url = URL::temporarySignedRoute('central.contacts.verify', now()->addMinutes(60), ['contact' => $contact, 'hash' => sha1($contact->value)]);
    $this->get($url)->assertRedirect(route('central.contacts.index'));
    expect($contact->fresh()->verified_at)->not->toBeNull();
    $this->get($url)->assertRedirect(route('central.contacts.index'));
    $this->actingAs($this->owner, 'central')->get($url)->assertForbidden();
});

test('expired or tampered secondary verification links are rejected without proof', function (): void {
    $contact = app(UserContactService::class)->add($this->admin, $this->admin, 'email', 'second@example.test');
    $url = URL::temporarySignedRoute('central.contacts.verify', now()->subMinute(), ['contact' => $contact, 'hash' => sha1($contact->value)]);
    $this->actingAs($this->admin, 'central')->get($url)->assertForbidden();
    $this->get($url.'&tampered=true')->assertForbidden();
    expect($contact->fresh()->verified_at)->toBeNull();
});

test('a limited pending session can verify its own secondary but gains no profile access or primary proof', function (): void {
    $contact = app(UserContactService::class)->add($this->admin, $this->owner, 'email', 'second@example.test');
    $this->owner->forceFill(['status' => \App\Enums\Central\Users\StatusEnum::PENDING, 'email_verified_at' => null])->save();
    $url = URL::temporarySignedRoute('central.contacts.verify', now()->addMinutes(60), ['contact' => $contact, 'hash' => sha1($contact->value)]);
    $this->actingAs($this->owner, 'central')->get($url)->assertRedirect(route('verification.notice'));
    expect($contact->fresh()->verified_at)->not->toBeNull()->and($this->owner->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->post(route('central.contacts.store'), ['kind' => 'email', 'value' => 'extra@example.test'])->assertForbidden();
});

test('a signed secondary link promoted to primary keeps the Fortify verification event and merchant response', function (): void {
    $service = app(UserContactService::class);
    $contact = $service->add($this->admin, $this->owner, 'email', 'second@example.test');
    $url = URL::temporarySignedRoute('central.contacts.verify', now()->addMinutes(60), ['contact' => $contact, 'hash' => sha1($contact->value)]);
    app(UserManagementService::class)->update($this->admin, $this->owner, new \App\DTOs\Central\Users\UserProfileData(
        $this->owner->last_name, $this->owner->first_name, $contact->value, $this->owner->phone, $this->owner->country->uuid,
    ));
    \Illuminate\Support\Facades\Event::fake([\Illuminate\Auth\Events\Verified::class]);
    $this->actingAs($this->owner->fresh(), 'central')->get($url)->assertRedirect(route('login'));
    $this->assertGuest('central');
    expect($this->owner->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and($this->owner->fresh()->status)->toBe(\App\Enums\Central\Users\StatusEnum::PENDING);
    \Illuminate\Support\Facades\Event::assertDispatchedOnce(\Illuminate\Auth\Events\Verified::class);
});

test('only verified secondary emails resolve through Fortify and the password broker', function (): void {
    $contact = app(UserContactService::class)->add($this->admin, $this->admin, 'email', 'second@example.test');
    $provider = Auth::createUserProvider('central_users');
    expect($provider->retrieveByCredentials(['email' => $contact->value]))->toBeNull();
    app(UserContactService::class)->verify($this->admin, $contact, sha1($contact->value));
    $this->post(route('login.store'), ['email' => ' SECOND@EXAMPLE.TEST ', 'password' => 'password'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->admin, 'central');
    $this->post(route('logout'));
    $this->post(route('password.email'), ['email' => $contact->value])->assertSessionHasNoErrors();
    Notification::assertSentTo($this->admin, ResetPassword::class, function ($notification, $channels, $user) use ($contact): bool {
        expect($user->getEmailForPasswordReset())->toBe($contact->value)
            ->and($user->routeNotificationForMail($notification))->toBe($contact->value);
        $this->resetToken = $notification->token;

        return true;
    });
    $this->post(route('password.update'), ['email' => $contact->value, 'token' => $this->resetToken,
        'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertSessionHasNoErrors();
    expect(Hash::check('new-password', $this->admin->fresh()->password))->toBeTrue();
    $this->post(route('password.update'), ['email' => $contact->value, 'token' => $this->resetToken,
        'password' => 'other-password', 'password_confirmation' => 'other-password'])->assertSessionHasErrors('email');
});

test('primary selection retains the former contact and enforces one primary per type', function (): void {
    $service = app(UserContactService::class);
    $contact = $service->add($this->admin, $this->admin, 'email', 'second@example.test');
    expect(fn () => $service->makePrimary($this->admin, $this->admin, $contact))->toThrow(ValidationException::class);
    $service->verify($this->admin, $contact, sha1($contact->value));
    $old = $this->admin->email;
    $this->actingAs($this->admin, 'central')->put(route('central.contacts.primary', $contact))->assertSessionHasNoErrors();
    expect($this->admin->fresh()->email)->toBe($contact->value)->and($this->admin->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($this->admin->contacts()->where('value', $old)->firstOrFail()->is_primary)->toBeFalse();
    expect($this->admin->contacts()->where('kind', 'email')->where('is_primary', true)->count())->toBe(1);
});

test('secondary removal revokes recovery and cannot remove the primary', function (): void {
    $service = app(UserContactService::class);
    $contact = $service->add($this->admin, $this->admin, 'email', 'second@example.test');
    $service->verify($this->admin, $contact, sha1($contact->value));
    $user = Auth::createUserProvider('central_users')->retrieveByCredentials(['email' => $contact->value]);
    Password::broker('central')->createToken($user);
    $this->actingAs($this->admin, 'central')->delete(route('central.contacts.destroy', $contact))->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $contact->value]);
    expect(Auth::createUserProvider('central_users')->retrieveByCredentials(['email' => $contact->value]))->toBeNull();
    expect(fn () => $service->remove($this->admin, $this->admin, $this->admin->contacts()->where('is_primary', true)->firstOrFail()))
        ->toThrow(ValidationException::class);
});

test('clearing a registered primary phone is rejected instead of leaving secondary phones without a primary', function (): void {
    expect(fn () => $this->owner->forceFill(['phone' => null, 'phone_country_id' => null])->save())->toThrow(ValidationException::class);
    expect($this->owner->fresh()->phone)->toBe('+213779107446');
});

test('management and search are authorized and include secondary contacts', function (): void {
    $service = app(UserContactService::class);
    $contact = $service->add($this->admin, $this->owner, 'email', 'second@example.test');
    expect(app(UserManagementService::class)->search($this->admin, $contact->value)->getCollection()->pluck('id')->all())->toBe([$this->owner->id]);
    $this->actingAs($this->admin, 'central')->get(route('central.users.contacts.index', $this->owner))->assertOk()->assertSee($contact->value);
    $this->get(route('central.contacts.index'))->assertOk();
    $this->actingAs($this->owner, 'central')->post(route('central.users.contacts.store', $this->admin), ['kind' => 'email', 'value' => 'attack@example.test'])
        ->assertForbidden();
    $this->delete(route('central.contacts.destroy', $contact))->assertSessionHasNoErrors();
    expect(UserContact::whereKey($contact->id)->exists())->toBeFalse();
});

test('an unrelated account cannot manipulate another users contact via a public UUID', function (): void {
    $contact = app(UserContactService::class)->add($this->admin, $this->admin, 'email', 'second@example.test');
    $this->actingAs($this->owner, 'central')->delete(route('central.contacts.destroy', $contact))->assertNotFound();
    expect($contact->fresh())->not->toBeNull();
});

test('inactive countries reject new secondary numbers and primary changes retain the old number', function (): void {
    $service = app(UserContactService::class);
    $france = Country::where('code', 'FR')->firstOrFail();
    expect(fn () => $service->add($this->admin, $this->owner, 'phone', '0612345678', $france->uuid))->toThrow(ValidationException::class);
    $france->forceFill(['is_active' => true])->save();
    $international = $service->add($this->admin, $this->owner, 'phone', '06 12 34 56 78', $france->uuid);
    expect($international->value)->toBe('+33612345678')->and($international->country_id)->toBe($france->id)
        ->and($this->owner->fresh()->country->code)->toBe('DZ');
    $contact = $service->add($this->admin, $this->owner, 'phone', '0555123456', $this->owner->country->uuid);
    $verifiedAt = now()->startOfSecond();
    $contact->forceFill(['verified_at' => $verifiedAt])->save();
    $this->actingAs($this->admin, 'central')->put(route('central.users.contacts.primary', ['user' => $this->owner, 'contact' => $contact]))
        ->assertSessionHasNoErrors();
    expect($this->owner->fresh()->phone)->toBe('+213555123456');
    expect($this->owner->fresh()->phone_verified_at)->toEqual($verifiedAt);
    expect($this->owner->contacts()->where('value', '+213779107446')->firstOrFail()->is_primary)->toBeFalse();
});
