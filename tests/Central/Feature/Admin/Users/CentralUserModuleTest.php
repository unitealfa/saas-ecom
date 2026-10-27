<?php

use App\DTOs\Central\Users\UserProfileData;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Users\User;
use App\Notifications\Central\Auth\AccountInvitation;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

function centralDecisionPayload(User $user): array
{
    return ['expected_status' => $user->status->value, 'legal_profile_version' => $user->legal_profile_version ?? 0];
}

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
});

test('catalogue updates the five existing permissions without replacing their identities', function (): void {
    $names = ['saas.users.view', 'saas.users.create', 'saas.users.update', 'saas.staff.invite', 'saas.users.roles.assign'];
    $before = Permission::whereIn('name', $names)->orderBy('id')->get()->map(fn ($permission): array => [$permission->id, $permission->uuid, $permission->name])->all();
    Permission::where('name', 'saas.users.create')->firstOrFail()->forceFill(['label' => 'Ancien libellé'])->save();
    $this->seed(AuthorizationSeeder::class);
    expect(Permission::whereIn('name', $names)->orderBy('id')->get()->map(fn ($permission): array => [$permission->id, $permission->uuid, $permission->name])->all())->toBe($before);
    expect(Permission::where('name', 'saas.users.create')->value('label'))->toBe('Ancien libellé');
});

test('legacy action names can change without losing their uuid grants or role signature', function (): void {
    $permission = Permission::where('name', 'saas.users.activate')->firstOrFail();
    $role = Role::createWithPermissions('existing-reactivation', ['saas.users.activate' => 9]);
    $uuid = $permission->uuid;
    $signature = $role->getAttribute('permission_signature');
    $permission->forceFill(['name' => 'saas.users.restore'])->save();
    $this->seed(AuthorizationSeeder::class);
    expect(Permission::where('name', 'saas.users.activate')->value('uuid'))->toBe($uuid);
    expect($role->fresh()->getAttribute('permission_signature'))->toBe($signature);
    expect($role->permissionComposition()->firstOrFail()->uuid)->toBe($uuid);
});

test('ordinary consultation cannot read the professional dossier or administrative history', function (): void {
    $reader = User::factory()->create();
    $reader->assignRole(Role::findByName('user-reader', 'central'));
    $target = User::factory()->create(['nif' => 'PRIVATE-NIF', 'legal_address' => 'PRIVATE-ADDRESS']);
    $this->actingAs($reader, 'central')->get(route('central.users.show', $target))->assertOk()
        ->assertDontSee('PRIVATE-NIF')->assertDontSee('PRIVATE-ADDRESS')->assertDontSee('Historique administratif');
    $this->get(route('central.users.history', $target))->assertForbidden();
    $this->post(route('central.users.legal.validate', $target), ['legal_profile_version' => 1])->assertForbidden();
});

test('professional validation records its version and reviewer and rejects a stale decision', function (): void {
    $target = User::factory()->create([
        'legal_form' => 'Entreprise individuelle', 'activity_nature' => 'Commerce',
        'legal_address' => 'Alger', 'registration_number' => 'RC123',
        'legal_profile_version' => 2, 'legal_verification_status' => VerificationStatusEnum::PENDING,
    ]);
    $this->actingAs($this->admin, 'central')->post(route('central.users.legal.validate', $target), [...centralDecisionPayload($target), 'legal_profile_version' => 1])
        ->assertSessionHasErrors('legal_profile_version');
    expect($target->fresh()->legal_verification_status)->toBe(VerificationStatusEnum::PENDING);
    $this->post(route('central.users.legal.validate', $target), centralDecisionPayload($target))->assertSessionHasNoErrors();
    expect($target->fresh()->legal_verification_status)->toBe(VerificationStatusEnum::VERIFIED);
    expect($target->fresh()->getAttribute('legal_verified_by_id'))->toBe($this->admin->id);
    expect($target->fresh()->getAttribute('legal_verified_at'))->not->toBeNull();
    $this->post(route('central.users.legal.reject', $target), [...centralDecisionPayload($target), 'reason' => 'Ancien formulaire'])
        ->assertForbidden();
    $this->put(route('central.users.update', $target), [
        'last_name' => $target->last_name, 'first_name' => $target->first_name, 'email' => $target->email,
        'country_uuid' => $target->country->uuid, 'legal_address' => 'Nouvelle adresse à examiner',
    ])->assertSessionHasNoErrors();
    $target = $target->fresh();
    $this->post(route('central.users.legal.reject', $target), [...centralDecisionPayload($target), 'reason' => 'Informations incorrectes'])
        ->assertForbidden();
    expect($target->fresh()->legal_verification_status)->toBe(VerificationStatusEnum::VERIFIED);
    expect($target->fresh()->getAttribute('legal_verified_at'))->not->toBeNull();
    $other = User::factory()->create();
    activity()->performedOn($other)->event('central_profile_updated')->log('OTHER-USER-HISTORY');
    $this->get(route('central.users.history', $target))->assertOk()->assertSee('Dossier validé avec l’approbation du compte.')
        ->assertDontSee('OTHER-USER-HISTORY');
});

test('an empty professional dossier can be validated with its current version', function (?int $version): void {
    $target = User::factory()->create([
        ...array_fill_keys(UserProfileData::PROFESSIONAL_FIELDS, null),
        'legal_profile_version' => $version, 'legal_verification_status' => VerificationStatusEnum::INCOMPLETE,
    ]);
    $this->actingAs($this->admin, 'central')->get(route('central.users.show', $target))
        ->assertSeeText('Approuver')->assertSee(route('central.users.approve', $target), false)->assertDontSee('Valider le dossier');
    $this->post(route('central.users.legal.validate', $target), centralDecisionPayload($target))
        ->assertRedirect(route('central.users.show', $target))->assertSessionHasNoErrors();

    $updated = $target->fresh();
    expect($updated->legal_verification_status)->toBe(VerificationStatusEnum::VERIFIED)
        ->and($updated->legal_profile_version)->toBe($version ?? 1)
        ->and($updated->getAttribute('legal_verified_by_id'))->toBe($this->admin->id)
        ->and($updated->legal_verified_at)->not->toBeNull();
    foreach (UserProfileData::PROFESSIONAL_FIELDS as $field) {
        expect($updated->getAttribute($field))->toBeNull();
    }
    expect(Activity::forSubject($target)->where('event', 'central_legal_validated')->firstOrFail()
        ->getProperty('legal_profile_version'))->toBe($version ?? 1);
    $this->post(route('central.users.legal.validate', $target), [...centralDecisionPayload($target), 'legal_profile_version' => 0])
        ->assertForbidden();
    expect(Activity::forSubject($target)->where('event', 'central_legal_validated')->count())->toBe(1);
})->with(['first decision' => null, 'legacy incomplete dossier' => 1]);

test('an invitation token verifies the current email once without a second verification message', function (): void {
    Notification::fake();
    $this->actingAs($this->admin, 'central')->post(route('central.users.store'), [
        'last_name' => 'Invité', 'first_name' => 'Karim', 'email' => 'invite@example.test',
        'country_uuid' => $this->admin->country->uuid, 'account_kind' => 'merchant',
    ])->assertSessionHasNoErrors();
    $target = User::where('email', 'invite@example.test')->firstOrFail();
    $token = null;
    Notification::assertSentTo($target, AccountInvitation::class, function ($notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });
    $this->post(route('logout'));
    $data = ['email' => $target->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
    $this->post(route('password.update'), $data)->assertSessionHasNoErrors();
    expect($target->fresh()->hasVerifiedEmail())->toBeTrue();
    Notification::assertNotSentTo($target, VerifyEmail::class);
    expect(Password::broker('central')->tokenExists($target, $token))->toBeFalse();
    $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
    expect(Activity::forSubject($target)->where('event', 'account_invitation_accepted')->count())->toBe(1);
});

test('a password reset alone does not verify a public account without an invitation', function (): void {
    Notification::fake();
    $target = User::factory()->unverified()->create();
    $token = Password::broker('central')->createToken($target);
    $this->post(route('password.update'), [
        'email' => $target->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();
    expect($target->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a token for an old email cannot verify the newly edited address', function (): void {
    Notification::fake();
    $target = User::factory()->unverified()->create();
    activity()->performedOn($target)->event('account_invited')->log('Invitation centrale.');
    $oldEmail = $target->email;
    $token = Password::broker('central')->createToken($target);
    $target->forceFill(['email' => 'new-address@example.test'])->save();
    $this->post(route('password.update'), [
        'email' => $oldEmail, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('email');
    expect($target->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('the pending list displays contact details without other accounts or private dossier fields', function (): void {
    $pending = User::factory()->create(['status' => StatusEnum::PENDING, 'phone' => '+213555111444', 'nif' => 'PRIVATE-PENDING-NIF']);
    $active = User::factory()->create();
    $this->actingAs($this->admin, 'central')->get(route('central.users.pending'))->assertOk()
        ->assertSee($pending->email)->assertSee('+213555111444')->assertSee($pending->country->name_fr)
        ->assertDontSee($active->email)->assertDontSee('PRIVATE-PENDING-NIF');
});

test('approval validates the dossier without verifying the email', function (): void {
    $approver = User::factory()->create();
    $approver->assignRole(Role::createWithPermissions('registration-approver', ['saas.users.approve' => 10, 'saas.users.view' => 10]));
    $target = User::factory()->unverified()->create(['status' => StatusEnum::PENDING, 'legal_verification_status' => VerificationStatusEnum::INCOMPLETE]);
    $this->actingAs($approver, 'central')->get(route('central.users.pending'))->assertOk();
    $this->post(route('central.users.approve', $target), centralDecisionPayload($target))->assertRedirect(route('central.users.pending'));
    expect($target->fresh()->status)->toBe(StatusEnum::ACTIVE);
    expect($target->fresh()->hasVerifiedEmail())->toBeFalse();
    expect($target->fresh()->legal_verification_status)->toBe(VerificationStatusEnum::VERIFIED);
    expect(Activity::forSubject($target)->where('event', 'central_account_approved')->count())->toBe(1);
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $target->email, 'password' => 'password'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($target, 'central');
    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
});

test('refusing an inscription keeps the account and limits login to refusal guidance', function (): void {
    $target = User::factory()->create(['status' => StatusEnum::PENDING]);
    $this->actingAs($this->admin, 'central')->from(route('central.users.pending'))->post(route('central.users.reject', $target), [...centralDecisionPayload($target), 'reason' => 'Demande refusée'])
        ->assertRedirect(route('central.users.pending'));
    expect($target->fresh()->status)->toBe(StatusEnum::REJECTED);
    expect($target->fresh()->trashed())->toBeFalse();
    expect(Activity::forSubject($target)->where('event', 'central_account_rejected')->count())->toBe(1);
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => $target->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect(route('registration.status'));
    $this->assertAuthenticatedAs($target, 'central');
    $this->get(route('registration.status'))->assertOk()->assertSee('Inscription refusée')->assertSee('Demande refusée');
});

test('approval and refusal are independent permissions and ordinary readers cannot decide', function (): void {
    $approver = User::factory()->create();
    $approver->assignRole(Role::createWithPermissions('approve-only', ['saas.users.approve' => 2]));
    $reader = User::factory()->create();
    $reader->assignRole(Role::findByName('user-reader', 'central'));
    $target = User::factory()->create(['status' => StatusEnum::PENDING]);
    expect(Gate::forUser($approver)->allows('approve', $target))->toBeTrue();
    expect(Gate::forUser($approver)->allows('reject', $target))->toBeFalse();
    expect(Gate::forUser($reader)->allows('approve', $target))->toBeFalse();
    $this->actingAs($reader, 'central')->post(route('central.users.approve', $target))->assertForbidden();
    $this->actingAs($approver, 'central')->post(route('central.users.reject', $target))->assertForbidden();
    expect($target->fresh()->status)->toBe(StatusEnum::PENDING);
});

test('a decided inscription cannot be approved or refused a second time', function (): void {
    $target = User::factory()->create(['status' => StatusEnum::PENDING]);
    $this->actingAs($this->admin, 'central')->post(route('central.users.approve', $target), centralDecisionPayload($target))->assertRedirect();
    $this->post(route('central.users.reject', $target))->assertForbidden();
    $this->post(route('central.users.approve', $target))->assertForbidden();
    expect($target->fresh()->status)->toBe(StatusEnum::ACTIVE);
    expect(Activity::forSubject($target)->whereIn('event', ['central_account_approved', 'central_account_rejected'])->count())->toBe(1);
});

test('an expired approval permission cannot consult or decide pending requests', function (): void {
    $this->freezeTime();
    $approver = User::factory()->create();
    $role = Role::createWithPermissions('expired-approver', ['saas.users.approve' => 1, 'saas.users.view' => 1]);
    $approver->assignRole($role);
    foreach ($approver->permissions()->get() as $permission) {
        $approver->permissions()->updateExistingPivot($permission->id, ['assigned_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);
    }
    $target = User::factory()->create(['status' => StatusEnum::PENDING]);
    $this->actingAs($approver, 'central')->get(route('central.users.pending'))->assertForbidden();
    $this->post(route('central.users.approve', $target), centralDecisionPayload($target))->assertForbidden();
    expect($target->fresh()->status)->toBe(StatusEnum::PENDING);
});

test('reactivation role assignment and shop creation cannot bypass the approval decision', function (StatusEnum $status): void {
    $target = User::factory()->create(['status' => $status]);
    $this->actingAs($this->admin, 'central')->patch(route('central.users.status', $target), ['status' => StatusEnum::ACTIVE->value])->assertForbidden();
    $this->get(route('central.staff.roles.edit', $target))->assertForbidden();
    $this->get(route('central.users.tenants.create', $target))->assertForbidden();
    expect($target->fresh()->status)->toBe($status);
})->with([StatusEnum::PENDING, StatusEnum::REJECTED]);
