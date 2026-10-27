<?php

use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Roles\Role;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Notifications\Central\Auth\AccountInvitation;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

function merchantDecisionSnapshot(User $user): array
{
    return ['expected_status' => $user->status->value, 'legal_profile_version' => $user->legal_profile_version ?? 0];
}

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
});

test('approval validates the account and dossier atomically without verifying email or creating a shop', function (): void {
    $merchant = User::factory()->unverified()->create(['status' => StatusEnum::PENDING]);
    $this->actingAs($this->admin, 'central')->post(route('central.users.approve', $merchant), merchantDecisionSnapshot($merchant))
        ->assertRedirect(route('central.users.index'))->assertSessionHasNoErrors();
    $updated = $merchant->fresh();
    expect($updated->status)->toBe(StatusEnum::ACTIVE)
        ->and($updated->legal_verification_status)->toBe(VerificationStatusEnum::VERIFIED)
        ->and($updated->legal_profile_version)->toBe(1)
        ->and($updated->legal_verified_at)->not->toBeNull()
        ->and($updated->legal_verified_by_id)->toBe($this->admin->id)
        ->and($updated->hasVerifiedEmail())->toBeFalse();
    expect(Tenant::count())->toBe(0);
    $this->get(route('central.users.show', $updated))->assertOk()
        ->assertDontSee('action="'.route('central.users.approve', $updated).'"', false)
        ->assertDontSee('action="'.route('central.users.reject', $updated).'"', false)
        ->assertDontSee('Valider le dossier')->assertDontSee('Rejeter le dossier');
    $this->get(route('central.users.pending'))->assertDontSee($updated->email);
});

test('controller corrections refuse external decision redirects', function (): void {
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING]);
    $this->actingAs($this->admin, 'central')->from('https://external.example/admin/users')
        ->post(route('central.users.approve', $merchant), merchantDecisionSnapshot($merchant))
        ->assertSessionHasNoErrors()->assertRedirect(route('central.users.index'));
});

test('controller corrections approve without requiring user listing access', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('approval-only', ['saas.users.approve' => 9999]));
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING]);

    $this->actingAs($actor, 'central')->from(route('central.users.pending'))
        ->post(route('central.users.approve', $merchant), merchantDecisionSnapshot($merchant))
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    expect($merchant->fresh()->status)->toBe(StatusEnum::ACTIVE);
    $this->get(route('central.users.index'))->assertForbidden();
});

test('controller corrections preserve legacy legal decisions under the current policy', function (): void {
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING]);
    $this->actingAs($this->admin, 'central')->post(route('central.users.legal.reject', $merchant), [
        ...merchantDecisionSnapshot($merchant), 'reason' => 'Vérification administrative.',
    ])->assertSessionHasNoErrors()->assertRedirect(route('central.users.show', $merchant));
    $merchant->refresh();
    $this->post(route('central.users.legal.validate', $merchant), merchantDecisionSnapshot($merchant))
        ->assertSessionHasNoErrors()->assertRedirect(route('central.users.show', $merchant));
    expect($merchant->fresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and($merchant->fresh()->legal_verification_status)->toBe(VerificationStatusEnum::VERIFIED);
});

test('merchant decisions return to the page that submitted the form', function (string $action, string $origin): void {
    $merchant = User::factory()->unverified()->create(['status' => StatusEnum::PENDING]);
    $previousUrl = $origin === 'show'
        ? route('central.users.show', $merchant)
        : route('central.users.'.$origin, ['search' => 'commerçant', 'page' => 2]);

    $this->actingAs($this->admin, 'central')->from($previousUrl)
        ->post(route('central.users.'.$action, $merchant), [
            ...merchantDecisionSnapshot($merchant),
            ...($action === 'reject' ? ['reason' => 'Dossier refusé.'] : []),
        ])
        ->assertRedirect($previousUrl)
        ->assertSessionHasNoErrors();

    expect($merchant->fresh()->status)->toBe($action === 'approve' ? StatusEnum::ACTIVE : StatusEnum::REJECTED);
})->with([
    'approve from users' => ['approve', 'index'],
    'reject from users' => ['reject', 'index'],
    'approve from pending registrations' => ['approve', 'pending'],
    'reject from pending registrations' => ['reject', 'pending'],
    'approve from user details' => ['approve', 'show'],
    'reject from user details' => ['reject', 'show'],
]);

test('refusal requires a nonempty reason at every decision entry point', function (string $routeName, mixed $reason): void {
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING]);
    $this->actingAs($this->admin, 'central')->postJson(route($routeName, $merchant), [
        ...merchantDecisionSnapshot($merchant), 'reason' => $reason,
    ])->assertUnprocessable()->assertJsonValidationErrors('reason');
    expect($merchant->fresh()->status)->toBe(StatusEnum::PENDING);
    expect(Activity::forSubject($merchant)->where('event', 'central_account_rejected')->count())->toBe(0);
})->with([
    'missing reason' => ['central.users.reject', null],
    'empty reason' => ['central.users.reject', ''],
    'whitespace reason' => ['central.users.reject', '   '],
    'legacy endpoint' => ['central.users.legal.reject', null],
]);

test('refusal and later approval synchronize both states and retain the previous author and reason', function (): void {
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING]);
    $this->actingAs($this->admin, 'central')->post(route('central.users.reject', $merchant), [
        ...merchantDecisionSnapshot($merchant), 'reason' => 'Merci de préciser votre demande.',
    ])->assertSessionHasNoErrors();
    $refused = $merchant->fresh();
    expect($refused->status)->toBe(StatusEnum::REJECTED)
        ->and($refused->legal_verification_status)->toBe(VerificationStatusEnum::FAILED)
        ->and($refused->legal_verified_at)->toBeNull();
    $this->get(route('central.users.show', $refused))
        ->assertSee('action="'.route('central.users.approve', $refused).'"', false)
        ->assertDontSee('action="'.route('central.users.reject', $refused).'"', false);
    $this->actingAs($refused, 'central')->getJson(route('registration.status'))
        ->assertJsonPath('reason', 'Merci de préciser votre demande.');
    $this->actingAs($this->admin, 'central')->post(route('central.users.approve', $refused), merchantDecisionSnapshot($refused))
        ->assertSessionHasNoErrors();
    expect($merchant->fresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and($merchant->fresh()->legal_verification_status)->toBe(VerificationStatusEnum::VERIFIED);
    $rejection = Activity::forSubject($merchant)->where('event', 'central_account_rejected')->firstOrFail();
    expect($rejection->causer_id)->toBe($this->admin->id)
        ->and($rejection->getProperty('reason'))->toBe('Merci de préciser votre demande.');
    $this->actingAs($merchant->fresh(), 'central')->getJson(route('registration.status'))
        ->assertJsonPath('state', 'setup')->assertJsonPath('reason', null);
    expect(Tenant::count())->toBe(0);
});

test('two outstanding opposite decisions cannot overwrite the first committed decision', function (bool $approveFirst): void {
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING]);
    $snapshot = merchantDecisionSnapshot($merchant);
    $this->actingAs($this->admin, 'central')->post(route($approveFirst ? 'central.users.approve' : 'central.users.reject', $merchant), [
        ...$snapshot, ...($approveFirst ? [] : ['reason' => 'Premier avis.']),
    ])->assertSessionHasNoErrors();
    $this->postJson(route($approveFirst ? 'central.users.reject' : 'central.users.approve', $merchant), [
        ...$snapshot, ...($approveFirst ? ['reason' => 'Ancien formulaire.'] : []),
    ])->assertStatus($approveFirst ? 403 : 422);
    expect($merchant->fresh()->status)->toBe($approveFirst ? StatusEnum::ACTIVE : StatusEnum::REJECTED)
        ->and($merchant->fresh()->legal_verification_status)->toBe($approveFirst ? VerificationStatusEnum::VERIFIED : VerificationStatusEnum::FAILED);
    expect(Activity::forSubject($merchant)->whereIn('event', ['central_account_approved', 'central_account_rejected'])->count())->toBe(1);
})->with(['approve first' => [true], 'refuse first' => [false]]);

test('legacy legal permission alone cannot approve an account through the former endpoint', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('legacy-reviewer', ['saas.users.legal.validate' => 9999]));
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING]);
    $this->actingAs($actor, 'central')->post(route('central.users.legal.validate', $merchant), merchantDecisionSnapshot($merchant))->assertForbidden();
    expect($merchant->fresh()->status)->toBe(StatusEnum::PENDING);
});

test('an administrative merchant is approved and invited before secure password setup and mandatory first shop', function (): void {
    Notification::fake();
    $creator = User::factory()->create();
    $creator->assignRole(Role::findByName('user-manager', 'central'));
    $payload = ['last_name' => 'Marchand', 'first_name' => 'Nour', 'email' => 'merchant-created@example.test',
        'country_uuid' => $creator->country->uuid, 'account_kind' => 'merchant', 'legal_form' => 'EURL', 'nif' => '001234'];
    $this->actingAs($creator, 'central')->post(route('central.users.store'), $payload)->assertSessionHasNoErrors();
    $merchant = User::where('email', $payload['email'])->firstOrFail();
    expect($merchant->status)->toBe(StatusEnum::ACTIVE)
        ->and($merchant->legal_verification_status)->toBe(VerificationStatusEnum::VERIFIED)
        ->and($merchant->legal_verified_by_id)->toBe($creator->id)
        ->and($merchant->hasVerifiedEmail())->toBeFalse()
        ->and($merchant->nif)->toBe('001234');
    expect(Tenant::count())->toBe(0);
    $this->get(route('central.users.pending'))->assertDontSee($merchant->email);
    $this->post(route('central.users.store'), $payload)->assertSessionHasErrors('email');
    $this->actingAs(User::factory()->create(), 'central')->post(route('central.users.store'), [...$payload, 'email' => 'not-allowed@example.test'])->assertForbidden();
    $token = null;
    Notification::assertSentTo(User::findOrFail($merchant->id), AccountInvitation::class, function ($notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });
    $this->post(route('logout'));
    $password = ['email' => $merchant->email, 'token' => $token, 'password' => 'chosen-password', 'password_confirmation' => 'chosen-password'];
    $this->post(route('password.update'), $password)->assertSessionHasNoErrors();
    expect($merchant->fresh()->hasVerifiedEmail())->toBeTrue();
    expect(Hash::check('chosen-password', $merchant->fresh()->password))->toBeTrue();
    $this->post(route('password.update'), $password)->assertSessionHasErrors('email');
    $this->post(route('login.store'), ['email' => $merchant->email, 'password' => 'chosen-password'])
        ->assertSessionHasNoErrors()->assertRedirect(route('owner.first-shop'));
    $this->get(route('owner.first-shop'))->assertOk()->assertSee('Nom de votre boutique');
    expect(Tenant::count())->toBe(0);
});

test('an expired activation invitation can be replaced without verifying the address early', function (): void {
    Notification::fake();
    $this->actingAs($this->admin, 'central')->post(route('central.users.store'), [
        'last_name' => 'Invité', 'email' => 'expired-invite@example.test',
        'country_uuid' => $this->admin->country->uuid, 'account_kind' => 'merchant',
    ])->assertSessionHasNoErrors();
    $merchant = User::where('email', 'expired-invite@example.test')->firstOrFail();
    $oldToken = null;
    Notification::assertSentTo(User::findOrFail($merchant->id), AccountInvitation::class, function ($notification) use (&$oldToken): bool {
        $oldToken = $notification->token;

        return true;
    });
    $this->post(route('logout'));
    $this->travel(config('auth.passwords.central.expire') + 1)->minutes();
    $this->post(route('password.update'), ['email' => $merchant->email, 'token' => $oldToken,
        'password' => 'chosen-password', 'password_confirmation' => 'chosen-password'])->assertSessionHasErrors('email');
    expect($merchant->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->actingAs($this->admin, 'central')->post(route('central.users.invitation', $merchant))->assertSessionHasNoErrors();
    $newToken = null;
    Notification::assertSentTo(User::findOrFail($merchant->id), AccountInvitation::class, function ($notification) use ($oldToken, &$newToken): bool {
        if ($notification->token === $oldToken) {
            return false;
        }
        $newToken = $notification->token;

        return true;
    });
    $this->post(route('logout'));
    $this->post(route('password.update'), ['email' => $merchant->email, 'token' => $newToken,
        'password' => 'chosen-password', 'password_confirmation' => 'chosen-password'])->assertSessionHasNoErrors();
    expect($merchant->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('administrative creation validates supplied professional fields before saving or inviting', function (array $fields): void {
    Notification::fake();
    $this->actingAs($this->admin, 'central')->postJson(route('central.users.store'), [
        'last_name' => 'Rejet des données invalides', 'email' => 'invalid-profile@example.test',
        'country_uuid' => $this->admin->country->uuid, 'account_kind' => 'merchant', ...$fields,
    ])->assertUnprocessable()->assertJsonValidationErrors(array_keys($fields));
    $this->assertDatabaseMissing('users', ['email' => 'invalid-profile@example.test']);
    Notification::assertNothingSent();
})->with(['negative capital' => [['share_capital' => '-1']], 'invalid legal form' => [['legal_form' => ['invalid']]]]);

test('professional information changes preserve approval and an existing suspension', function (): void {
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING]);
    $this->actingAs($this->admin, 'central')->post(route('central.users.approve', $merchant), merchantDecisionSnapshot($merchant))
        ->assertSessionHasNoErrors();
    $this->patch(route('central.users.status', $merchant), ['status' => StatusEnum::SUSPENDED->value])->assertSessionHasNoErrors();
    $this->put(route('central.users.update', $merchant), [
        'last_name' => $merchant->last_name, 'first_name' => $merchant->first_name,
        'email' => $merchant->email, 'country_uuid' => $merchant->country->uuid,
        'legal_address' => 'Nouvelle adresse professionnelle à examiner',
    ])->assertSessionHasNoErrors();
    expect($merchant->fresh()->status)->toBe(StatusEnum::SUSPENDED)
        ->and($merchant->fresh()->legal_verification_status)->toBe(VerificationStatusEnum::VERIFIED)
        ->and($merchant->fresh()->legal_profile_version)->toBe(2);
    $this->patch(route('central.users.status', $merchant), ['status' => StatusEnum::ACTIVE->value])->assertSessionHasNoErrors();
    expect($merchant->fresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and($merchant->fresh()->legal_verification_status)->toBe(VerificationStatusEnum::VERIFIED);
});

test('a pending profile edit versions the dossier and rejects an older decision form', function (): void {
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING,
        'legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::PENDING]);
    $snapshot = merchantDecisionSnapshot($merchant);
    $this->actingAs($this->admin, 'central')->put(route('central.users.update', $merchant), [
        'last_name' => 'Nom rectifié', 'email' => $merchant->email,
        'country_uuid' => $merchant->country->uuid,
    ])->assertSessionHasNoErrors();
    $this->post(route('central.users.approve', $merchant), $snapshot)
        ->assertSessionHasErrors('legal_profile_version');
    expect($merchant->fresh()->status)->toBe(StatusEnum::PENDING)
        ->and($merchant->fresh()->legal_profile_version)->toBe(2);
});

test('protected root accounts cannot be approved or rejected as merchants', function (string $routeName): void {
    $target = User::factory()->create();
    $target->assignRole(Role::findByName('root', 'central'));
    $before = $target->fresh()->getRawOriginal();
    $this->actingAs($this->admin, 'central')->post(route($routeName, $target), [
        ...merchantDecisionSnapshot($target), ...(str_ends_with($routeName, 'reject') ? ['reason' => 'Tentative interdite'] : []),
    ])->assertForbidden();
    expect($target->fresh()->getRawOriginal())->toBe($before);
})->with(['central.users.approve', 'central.users.reject', 'central.users.legal.validate', 'central.users.legal.reject']);
