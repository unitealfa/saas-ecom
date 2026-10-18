<?php

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Activity;
use App\Models\Central\Role;
use App\Models\User;
use App\Notifications\Central\Auth\AccountInvitation;
use App\Services\Central\Auth\AccountMailDelivery;
use App\Services\Central\Authorization\CentralPermissionResolver;
use App\Services\Central\Users\UserManagementService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
    Notification::fake();
});

function staffTestAccount(bool $verified = true): User
{
    $user = $verified ? User::factory()->create() : User::factory()->unverified()->create();
    $user->assignRole(Role::findByName('user-reader', 'central'));

    return $user;
}

function staffTestProfile(User $user): array
{
    return ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email,
        'phone' => $user->phone, 'country_uuid' => $user->country->uuid, 'locale' => 'fr'];
}

test('staff listing separates merchants archived accounts and protected root without exposing secrets', function (): void {
    $staff = staffTestAccount();
    $merchant = User::factory()->create();
    $archived = staffTestAccount();
    $archived->forceFill(['status' => StatusEnum::DELETED])->save();
    $archived->delete();

    $this->actingAs($this->admin, 'central')->get(route('central.staff.index'))->assertOk()
        ->assertSee($staff->email)->assertSee($this->admin->email)->assertDontSee($merchant->email)->assertDontSee($archived->email);
    Livewire::actingAs($this->admin, 'central')->test('central::pages.admin.staff.index')
        ->set('status', '4')->assertSee($archived->email)->assertDontSee($staff->email);
    $this->get(route('central.staff.show', $staff))->assertOk()->assertDontSee($staff->password)
        ->assertDontSeeText($staff->uuid)->assertDontSee('Boutiques');
});

test('staff pages refuse guests merchants and ordinary readers including direct URLs', function (): void {
    $staff = staffTestAccount();
    $this->get(route('central.staff.index'))->assertRedirect(route('login'));
    $merchant = User::factory()->create();
    $this->actingAs($merchant, 'central')->get(route('central.staff.index'))->assertForbidden();
    $this->get(route('central.staff.show', $staff))->assertForbidden();
    $this->actingAs($staff, 'central')->get(route('central.staff.index'))->assertForbidden();
    $this->get(route('central.staff.create'))->assertForbidden();
    $this->put(route('central.staff.update', $this->admin), staffTestProfile($this->admin))->assertForbidden();
    Notification::assertNothingSent();
});

test('staff creation uses existing Spatie roles and Fortify invitation without an administrative password', function (): void {
    $role = Role::findByName('user-reader', 'central');
    $this->actingAs($this->admin, 'central')->get(route('central.staff.create'))->assertOk();
    $this->post(route('central.staff.store'), [
        'last_name' => 'Administrator', 'email' => 'staff-new@example.test', 'phone' => '+213555111222',
        'country_uuid' => $this->admin->country->uuid, 'roles' => [$role->uuid],
    ])->assertSessionHasNoErrors()->assertRedirect(route('central.staff.index'));
    $staff = User::where('email', 'staff-new@example.test')->firstOrFail();
    expect($staff->hasRole($role))->toBeTrue();
    expect($staff->country_id)->toBe($this->admin->country_id);
    expect($staff->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($staff, AccountInvitation::class, fn ($notification): bool => Password::broker('central')->tokenExists($staff, $notification->token));
    expect(Activity::forSubject($staff)->where('event', 'account_invited')->count())->toBe(1);
});

test('staff creation refuses empty roles a supplied password and a protected root role', function (): void {
    $data = ['last_name' => 'Invalid', 'email' => 'invalid-staff@example.test', 'country_uuid' => $this->admin->country->uuid];
    $this->actingAs($this->admin, 'central')->post(route('central.staff.store'), $data)->assertSessionHasErrors('roles');
    $this->post(route('central.staff.store'), [...$data, 'roles' => [Role::findByName('root')->uuid], 'password' => 'chosen-by-admin'])
        ->assertSessionHasErrors(['roles.0', 'password']);
    expect(User::where('email', $data['email'])->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('staff role replacement needs assignment permission but not a new invitation and preserves kept dates', function (): void {
    $this->freezeTime();
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('staff-role-manager', ['saas.users.roles.assign' => 9999, 'saas.users.view' => 9999]));
    $staff = staffTestAccount();
    $role = $staff->roles()->firstOrFail();
    $assignedAt = $role->pivot->assigned_at;
    $this->travel(2)->days();
    expect($actor->checkPermissionTo('saas.staff.invite', 'central'))->toBeFalse();
    $this->actingAs($actor, 'central')->get(route('central.staff.roles.edit', $staff))->assertOk();
    $this->put(route('central.staff.roles.update', $staff), ['roles' => [$role->uuid]])->assertSessionHasNoErrors();
    expect($staff->roles()->firstOrFail()->pivot->assigned_at)->toEqual($assignedAt);
    expect(Activity::forSubject($staff)->where('event', 'central_roles_updated')->count())->toBe(1);
});

test('derived consultation does not permit delegation of a permission that is no longer owned', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('staff-creator-without-explicit-read', ['saas.users.create' => 9999, 'saas.users.roles.assign' => 9999]));
    $staff = staffTestAccount();
    expect(Gate::forUser($actor)->allows('viewAny', User::class))->toBeTrue();
    expect($actor->checkPermissionTo('saas.users.view', 'central'))->toBeFalse();
    $this->actingAs($actor, 'central')->put(route('central.staff.roles.update', $staff), ['roles' => [Role::findByName('user-reader')->uuid]])->assertForbidden();
    expect($staff->roles()->pluck('name')->all())->toBe(['user-reader']);
    expect(Activity::forSubject($staff)->where('event', 'central_roles_updated')->count())->toBe(0);
});

test('staff role replacement refuses overlaps root assignments and merchant targets', function (): void {
    $staff = staffTestAccount();
    $merchant = User::factory()->create();
    $this->actingAs($this->admin, 'central')->put(route('central.staff.roles.update', $staff), [
        'roles' => [Role::findByName('user-reader')->uuid, Role::findByName('user-manager')->uuid],
    ])->assertSessionHasErrors('roles');
    $this->put(route('central.staff.roles.update', $staff), ['roles' => [Role::findByName('root')->uuid]])->assertSessionHasErrors('roles.0');
    $this->get(route('central.staff.roles.edit', $merchant))->assertForbidden();
    expect($staff->roles()->pluck('name')->all())->toBe(['user-reader']);
});

test('removing all staff roles revokes access without deleting the central account', function (): void {
    $staff = staffTestAccount();
    $staff->givePermissionTo('saas.countries.view');
    $this->actingAs($this->admin, 'central')->put(route('central.staff.roles.update', $staff), ['roles' => []])->assertRedirect(route('central.staff.index'));
    expect($staff->fresh()->roles()->exists())->toBeFalse();
    expect($staff->fresh()->permissions()->exists())->toBeFalse();
    expect($staff->fresh()->trashed())->toBeFalse();
    $event = Activity::forSubject($staff)->where('event', 'central_staff_roles_replaced')->firstOrFail();
    expect($event->getProperty('before')[0]['role_uuid'])->toBe(Role::findByName('user-reader')->uuid);
    expect($event->getProperty('after'))->toBe([]);
    $this->get(route('central.staff.show', $staff))->assertForbidden();
    $this->get(route('central.users.show', $staff))->assertOk();
});

test('explicit role renewal restarts expiry once with a reason and refuses a stale double submission', function (): void {
    $this->travelTo(now()->startOfSecond());
    $staff = User::factory()->create();
    $role = Role::createWithPermissions('one-day-reader', ['saas.users.view' => 1]);
    $staff->assignRole($role);
    $original = $staff->roles()->firstOrFail()->pivot->assigned_at->toIso8601String();
    $this->travel(2)->days();
    expect($staff->checkPermissionTo('saas.users.view', 'central'))->toBeFalse();
    $url = route('central.staff.roles.renew', [$staff, $role]);
    $this->actingAs($this->admin, 'central')->post($url, ['expected_assigned_at' => $original])->assertSessionHasErrors('reason');
    $data = ['expected_assigned_at' => $original, 'reason' => 'Renouvellement administratif confirmé'];
    $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirect(route('central.staff.show', $staff));
    expect($staff->fresh()->checkPermissionTo('saas.users.view', 'central'))->toBeTrue();
    $this->post($url, $data)->assertSessionHasErrors('role');
    expect(Activity::forSubject($staff)->where('event', 'central_role_assignment_renewed')->count())->toBe(1);
    expect($staff->roles()->firstOrFail()->pivot->assigned_at)->toEqual(now());
});

test('effective permission display matches Spatie expiry and escapes role and account labels', function (): void {
    $this->freezeTime();
    $staff = User::factory()->create(['first_name' => '<script>staff()</script>']);
    $role = Role::createWithPermissions('short-staff-reader', ['saas.users.view' => 1]);
    $role->forceFill(['label' => '<script>role()</script>'])->save();
    $staff->assignRole($role);
    $this->travel(1)->days();
    $permission = app(CentralPermissionResolver::class)->forStaff($this->admin, $staff)->firstWhere('code', 'saas.users.view');
    expect($permission['effective'])->toBeFalse();
    expect($staff->checkPermissionTo('saas.users.view', 'central'))->toBeFalse();
    $this->actingAs($this->admin, 'central')->get(route('central.staff.permissions.index', $staff))->assertOk()
        ->assertSee(route('central.staff.roles.edit', $staff), false)
        ->assertSee('Non autorisé')->assertSee('&lt;script&gt;role()', escape: false)
        ->assertDontSee('<script>role()</script>', escape: false)->assertDontSee('<script>staff()</script>', escape: false);
});

test('permission inspection requires its own permission and expired management rights deny writes', function (): void {
    $this->freezeTime();
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('short-staff-editor', ['saas.staff.update' => 1, 'saas.users.view' => 9999]));
    $staff = staffTestAccount();
    $this->actingAs($actor, 'central')->get(route('central.staff.index'))->assertOk();
    $this->get(route('central.staff.edit', $staff))->assertOk();
    $this->get(route('central.staff.permissions.index', $staff))->assertForbidden();
    $component = Livewire::actingAs($actor, 'central')->test('central::pages.admin.staff.index');
    $this->travel(1)->days();
    $this->put(route('central.staff.update', $staff), staffTestProfile($staff))->assertForbidden();
    $this->get(route('central.staff.index'))->assertForbidden();
    $component->set('search', 'expired')->assertForbidden();
});

test('staff invitation resend has its own permission and refuses verified or suspended recipients', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('staff-invitation-resender', ['saas.staff.invite.resend' => 9999, 'saas.users.view' => 9999]));
    $staff = staffTestAccount(false);
    $old = Password::broker('central')->createToken($staff);
    $this->actingAs($actor, 'central')->get(route('central.staff.show', $staff))->assertOk()->assertSee('Renvoyer l’invitation');
    $this->post(route('central.staff.invitation', $staff))->assertSessionHasNoErrors();
    expect(Password::broker('central')->tokenExists($staff, $old))->toBeFalse();
    Notification::assertSentTo(App\Models\Central\User::findOrFail($staff->id), AccountInvitation::class);
    $verified = staffTestAccount();
    $this->post(route('central.staff.invitation', $verified))->assertForbidden();
    $staff->forceFill(['status' => StatusEnum::SUSPENDED])->save();
    $this->post(route('central.staff.invitation', $staff))->assertForbidden();
});

test('staff profile changes preserve role and password and report an email transport failure', function (): void {
    $staff = staffTestAccount(false);
    $password = $staff->password;
    $this->mock(AccountMailDelivery::class, function ($mock): void {
        $mock->shouldReceive('invitation')->once()->andReturn(false);
    });
    $this->actingAs($this->admin, 'central')->get(route('central.staff.edit', $staff))->assertOk();
    $this->put(route('central.staff.update', $staff), [
        ...staffTestProfile($staff), 'email' => 'changed-staff@example.test', 'last_name' => 'Changed', 'phone' => '0123456789',
    ])->assertSessionHasNoErrors()->assertSessionHas('warning');
    expect($staff->fresh()->last_name)->toBe('Changed');
    expect($staff->fresh()->email)->toBe('changed-staff@example.test');
    expect($staff->fresh()->password)->toBe($password);
    expect($staff->fresh()->hasRole('user-reader'))->toBeTrue();
    expect($staff->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->put(route('central.staff.update', $staff), [...staffTestProfile($staff->fresh()), 'legal_form' => 'Injected', 'status' => 4])
        ->assertSessionHasErrors(['legal_form', 'status']);
});

test('staff suspension invalidates central access and archive preserves roles and history', function (): void {
    $staff = staffTestAccount();
    $token = Password::broker('central')->createToken($staff);
    $db = $staff->getConnection();
    $db->table('sessions')->insert(['id' => (string) Str::uuid(), 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);
    $this->actingAs($this->admin, 'central')->patch(route('central.staff.status', $staff), ['status' => 3])->assertSessionHasNoErrors();
    expect($staff->fresh()->status)->toBe(StatusEnum::SUSPENDED);
    expect($staff->fresh()->remember_token)->toBeNull();
    expect($db->table('sessions')->where('user_id', $staff->id)->count())->toBe(0);
    expect(Password::broker('central')->tokenExists($staff, $token))->toBeFalse();
    $this->patch(route('central.staff.status', $staff), ['status' => 1])->assertSessionHasNoErrors();
    expect($staff->fresh()->status)->toBe(StatusEnum::ACTIVE);
    $this->delete(route('central.staff.destroy', $staff))->assertRedirect(route('central.staff.index'));
    $archived = App\Models\Central\User::withTrashed()->findOrFail($staff->id);
    expect($archived->trashed())->toBeTrue();
    expect($archived->status)->toBe(StatusEnum::DELETED);
    expect($archived->roles()->count())->toBe(1);
    expect(Activity::forSubject($archived)->where('event', 'central_account_archived')->count())->toBe(1);
});

test('staff mutations protect root self assignment and more privileged targets', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('limited-staff-admin', [
        'saas.staff.update' => 9999, 'saas.staff.suspend' => 9999, 'saas.staff.activate' => 9999,
        'saas.staff.archive' => 9999, 'saas.users.roles.assign' => 9999, 'saas.staff.assignments.renew' => 9999,
    ]));
    foreach (['suspendStaff', 'activateStaff', 'archiveStaff', 'assignStaffRoles', 'renewStaffAssignments'] as $ability) {
        expect(Gate::forUser($this->admin)->allows($ability, $this->admin))->toBeFalse();
        expect(Gate::forUser($actor)->allows($ability, $this->admin))->toBeFalse();
    }
    expect(Gate::forUser($actor)->allows('assignStaffRoles', $actor))->toBeFalse();
    $staff = staffTestAccount();
    $this->actingAs($actor, 'central')->put(route('central.staff.update', $staff), staffTestProfile($staff))->assertForbidden();
    $this->delete(route('central.staff.destroy', $this->admin))->assertForbidden();
    expect($this->admin->fresh()->trashed())->toBeFalse();
});

test('renewing an existing role needs renewal permission rather than permission to assign a new role', function (): void {
    $this->travelTo(now()->startOfSecond());
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('staff-renewer', ['saas.staff.assignments.renew' => 9999, 'saas.users.view' => 9999]));
    $staff = staffTestAccount();
    $role = $staff->roles()->firstOrFail();
    $original = $role->pivot->assigned_at->toIso8601String();
    $this->travel(1)->days();
    expect($actor->checkPermissionTo('saas.users.roles.assign', 'central'))->toBeFalse();

    $this->actingAs($actor, 'central')->post(route('central.staff.roles.renew', [$staff, $role]), [
        'reason' => 'Prolongation autorisée', 'expected_assigned_at' => $original,
    ])->assertSessionHasNoErrors()->assertRedirect(route('central.staff.show', $staff));
    expect($staff->roles()->firstOrFail()->pivot->assigned_at)->toEqual(now());
    expect(Activity::forSubject($staff)->where('event', 'central_role_assignment_renewed')->count())->toBe(1);
});

test('legacy users invitation cannot bypass the separate staff resend permission', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('staff-editor-without-resend', [
        'saas.staff.update' => 9999, 'saas.users.view' => 9999,
    ]));
    $staff = staffTestAccount(false);
    $token = Password::broker('central')->createToken($staff);

    expect(Gate::forUser($actor)->allows('updateStaff', $staff))->toBeTrue();
    expect(Gate::forUser($actor)->allows('resendStaffInvitation', $staff))->toBeFalse();
    $this->actingAs($actor, 'central')->post(route('central.users.invitation', $staff))->assertForbidden();
    expect(fn () => app(UserManagementService::class)->resendInvitation($actor, $staff))
        ->toThrow(AuthorizationException::class);
    $this->get(route('central.users.show', $staff))->assertOk()->assertDontSee('Renvoyer l’invitation');
    expect(Password::broker('central')->tokenExists($staff, $token))->toBeTrue();
    expect(Activity::forSubject($staff)->where('event', 'account_invitation_requested')->count())->toBe(0);
    Notification::assertNothingSent();
});

test('legacy users invitation accepts the staff resend permission without granting profile editing', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('staff-resender-without-edit', [
        'saas.staff.invite.resend' => 9999, 'saas.users.view' => 9999,
    ]));
    $staff = staffTestAccount(false);

    expect(Gate::forUser($actor)->allows('update', $staff))->toBeFalse();
    $this->actingAs($actor, 'central')->get(route('central.users.show', $staff))->assertOk()->assertSee('Renvoyer l’invitation');
    $this->post(route('central.users.invitation', $staff))->assertSessionHasNoErrors()
        ->assertRedirect(route('central.users.show', $staff));
    Notification::assertSentTo(App\Models\Central\User::findOrFail($staff->id), AccountInvitation::class);
    expect(Activity::forSubject($staff)->where('event', 'account_invitation_requested')->count())->toBe(1);
});

test('legacy users invitation refuses staff recipients whose account is not active', function (StatusEnum $status): void {
    $staff = staffTestAccount(false);
    $staff->forceFill(['status' => $status])->save();
    $token = Password::broker('central')->createToken($staff);

    $this->actingAs($this->admin, 'central')->post(route('central.users.invitation', $staff))->assertForbidden();
    expect(Password::broker('central')->tokenExists($staff, $token))->toBeTrue();
    Notification::assertNothingSent();
})->with([StatusEnum::INACTIVE, StatusEnum::SUSPENDED, StatusEnum::REJECTED]);

test('an expired unrelated permission does not prevent a still authorized staff member editing their own profile', function (): void {
    $this->freezeTime();
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('self-editor-with-expiring-read', [
        'saas.staff.update' => 9999, 'saas.users.view' => 1,
    ]));
    $password = $actor->password;
    $this->travel(2)->days();

    expect($actor->checkPermissionTo('saas.users.view', 'central'))->toBeFalse();
    expect($actor->checkPermissionTo('saas.staff.update', 'central'))->toBeTrue();
    $this->actingAs($actor, 'central')->get(route('central.staff.edit', $actor))->assertOk();
    $this->put(route('central.staff.update', $actor), [
        ...staffTestProfile($actor), 'last_name' => 'Self-edited',
    ])->assertSessionHasNoErrors();
    expect($actor->fresh()->last_name)->toBe('Self-edited');
    expect($actor->fresh()->password)->toBe($password);
    expect($actor->fresh()->checkPermissionTo('saas.users.view', 'central'))->toBeFalse();
    $this->put(route('central.staff.roles.update', $actor), ['roles' => []])->assertForbidden();
    expect($actor->fresh()->roles()->count())->toBe(1);
});
