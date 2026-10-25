<?php

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Activity;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\User;
use App\Notifications\Central\Auth\AccountInvitation;
use App\Services\Central\Auth\AccountMailDelivery;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Authorization\CentralPermissionResolver;
use App\Services\Central\Authorization\CentralRoleAssignmentService;
use App\Services\Central\Users\StaffManagementService;
use App\Services\Central\Users\UserManagementService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
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

test('saving staff roles preserves roles excluded from the delegable catalogue and direct grants', function (): void {
    $this->freezeTime();
    $legacy = Permission::create(['name' => 'saas.legacy.operation', 'label' => 'Legacy']);
    $hiddenRole = Role::createWithPermissions('legacy-profile', [$legacy->name => 7]);
    $staff = staffTestAccount();
    $staff->assignRole($hiddenRole);
    $staff->givePermissionTo('saas.countries.view');
    $reader = Role::findByName('user-reader', 'central');
    $before = $staff->roles()->whereKey($hiddenRole->id)->first()->pivot->assigned_at;
    $direct = $staff->permissions()->first()->pivot->expires_at;
    expect(app(UserManagementService::class)->availableRoles($this->admin)->contains('id', $hiddenRole->id))->toBeFalse();
    $this->travel(1)->days();
    $this->actingAs($this->admin, 'central')->put(route('central.staff.roles.update', $staff), ['roles' => []])->assertSessionHasNoErrors();
    expect($staff->fresh()->hasRole($hiddenRole))->toBeTrue()
        ->and($staff->fresh()->hasRole($reader))->toBeFalse()
        ->and($staff->roles()->first()->pivot->assigned_at)->toEqual($before)
        ->and($staff->permissions()->first()->pivot->expires_at)->toEqual($direct)
        ->and($staff->fresh()->can($legacy->name))->toBeTrue()
        ->and($staff->fresh()->can('saas.countries.view'))->toBeTrue();
});

test('legacy users role page and mutation are removed while staff role management remains available', function (): void {
    $staff = staffTestAccount();
    $legacyPath = '/admin/users/'.$staff->uuid.'/roles';
    $this->actingAs($this->admin, 'central')->get($legacyPath)->assertNotFound();
    $this->put($legacyPath, ['roles' => []])->assertNotFound();
    $this->get(route('central.users.show', $staff))->assertDontSee($legacyPath, false)
        ->assertSee(route('central.staff.permissions.index', $staff), false);
    $this->get(route('central.users.index'))->assertDontSee($legacyPath, false);
    $this->get(route('central.staff.roles.edit', $staff))->assertSee('Enregistrer les rôles');
    expect($staff->fresh()->hasRole('user-reader'))->toBeTrue();
});

test('permission display follows Gate for valid expired and suspended direct grants', function (): void {
    $this->freezeTime();
    $staff = staffTestAccount();
    $staff->givePermissionTo('saas.countries.view');
    $permission = $staff->permissions()->where('name', 'saas.countries.view')->firstOrFail();
    $staff->permissions()->updateExistingPivot($permission->id, ['expires_at' => now()->addDay()]);
    $resolver = app(CentralPermissionResolver::class);
    foreach ([0, 1] as $days) {
        if ($days) {
            $this->travel(1)->days();
        }
        $row = $resolver->forStaff($this->admin, $staff)->firstWhere('code', 'saas.countries.view');
        expect($row['effective'])->toBe($staff->fresh()->can('saas.countries.view'))->toBe($days === 0);
    }
    $staff->forceFill(['status' => StatusEnum::SUSPENDED])->save();
    foreach ($resolver->forStaff($this->admin, $staff) as $row) {
        expect($row['effective'])->toBeFalse()->toBe($staff->fresh()->can($row['code']));
    }
});

test('central mutations roll back permissions and audit on the actual model connection', function (): void {
    $staff = staffTestAccount();
    $connection = $staff->getConnection();
    expect(DB::getDefaultConnection())->toBe(config('tenancy.database.central_connection'))
        ->and(DB::connection())->toBe($connection);
    $events = Activity::count();
    try {
        app(AuthorizationMutation::class)->transaction(function () use ($staff, $connection): void {
            expect($connection->transactionLevel())->toBeGreaterThan(0);
            $staff->givePermissionTo('saas.countries.view');
            activity()->performedOn($staff)->event('rollback_probe')->log('Isolated rollback test');
            throw new RuntimeException('rollback');
        });
        $this->fail('The mutation must fail.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback');
    }
    expect($staff->fresh()->can('saas.countries.view'))->toBeFalse()
        ->and($staff->permissions()->count())->toBe(1)
        ->and($staff->fresh()->can('saas.users.view'))->toBeTrue()
        ->and(Activity::count())->toBe($events);
});

test('central mutation authorizes the actor and target before executing the write', function (): void {
    $actor = staffTestAccount();
    $staff = staffTestAccount();
    $executed = false;
    expect(fn () => app(AuthorizationMutation::class)->run(
        actor: $actor,
        ability: 'updateStaff',
        loadTarget: fn (): User => User::whereKey($staff->id)->lockForUpdate()->firstOrFail(),
        mutation: function (User $target) use (&$executed): void {
            $executed = true;
            $target->update(['last_name' => 'Interdit']);
        },
    ))->toThrow(AuthorizationException::class);
    expect($executed)->toBeFalse();
    expect($staff->fresh()->last_name)->not->toBe('Interdit');
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

test('controller corrections renew roles with renewal permission alone', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('renewal-only', [
        'saas.staff.assignments.renew' => 9999, 'saas.users.view' => 9999,
    ]));
    $staff = staffTestAccount();
    $role = $staff->roles()->firstOrFail();
    $original = $role->pivot->assigned_at->toIso8601String();
    $this->travel(1)->days();

    $this->actingAs($actor, 'central')->post(route('central.staff.roles.renew', [$staff, $role]), [
        'reason' => 'Renouvellement autorisé.', 'expected_assigned_at' => $original,
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    expect(Activity::forSubject($staff)->where('event', 'central_role_assignment_renewed')->count())->toBe(1);
    $this->get(route('central.staff.roles.edit', $staff))->assertForbidden();
});

test('controller corrections report a single warning after an email update fails', function (string $context): void {
    $target = $context === 'staff' ? staffTestAccount() : User::factory()->create();
    $payload = [...staffTestProfile($target), 'email' => 'updated-contact@example.test'];
    $this->mock(AccountMailDelivery::class)->shouldReceive('verification')->once()->andReturn(false);

    $this->actingAs($this->admin, 'central')->put(route('central.'.$context.'.update', $target), $payload)
        ->assertSessionHasNoErrors()->assertSessionHas('warning')->assertSessionMissing('status');
    expect($target->fresh()->email)->toBe($payload['email'])
        ->and($target->fresh()->status)->toBe(StatusEnum::ACTIVE);
})->with(['users', 'staff']);

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
        ->assertDontSeeText($staff->uuid)
        ->assertDontSee('href="'.route('central.users.tenants.create', $staff).'"', false)
        ->assertDontSee('Priorités des boutiques de '.$staff->name);
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
    expect(Gate::forUser($actor)->allows('viewAny', User::class))->toBeFalse();
    expect($actor->checkPermissionTo('saas.users.view', 'central'))->toBeFalse();
    $this->actingAs($actor, 'central')->put(route('central.staff.roles.update', $staff), ['roles' => [Role::findByName('user-reader')->uuid]])->assertForbidden();
    expect($staff->roles()->pluck('name')->all())->toBe(['user-reader']);
    expect(Activity::forSubject($staff)->where('event', 'central_roles_updated')->count())->toBe(0);
});

test('shared profiles may reuse defaults without duplicate direct grants while root and merchants remain protected', function (): void {
    $staff = staffTestAccount();
    $merchant = User::factory()->create();
    $this->actingAs($this->admin, 'central')->put(route('central.staff.roles.update', $staff), [
        'roles' => [Role::findByName('user-reader')->uuid, Role::findByName('user-manager')->uuid],
    ])->assertSessionHasNoErrors();
    $this->put(route('central.staff.roles.update', $staff), ['roles' => [Role::findByName('root')->uuid]])->assertSessionHasErrors('roles.0');
    $this->get(route('central.staff.roles.edit', $merchant))->assertForbidden();
    expect($staff->roles()->pluck('name')->all())->toBe(['user-manager', 'user-reader']);
    expect($staff->permissions()->where('name', 'saas.users.view')->count())->toBe(1);
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
    $actor->assignRole(Role::createWithPermissions('short-staff-editor', ['saas.staff.update' => 1, 'saas.staff.view' => 1, 'saas.users.view' => 9999]));
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
    $actor->assignRole(Role::createWithPermissions('staff-invitation-resender', ['saas.staff.invite.resend' => 9999, 'saas.staff.view' => 9999, 'saas.users.view' => 9999]));
    $staff = staffTestAccount(false);
    $old = Password::broker('central')->createToken($staff);
    $this->actingAs($actor, 'central')->get(route('central.staff.show', $staff))->assertOk()->assertSee('Renvoyer l’invitation');
    $this->post(route('central.staff.invitation', $staff))->assertSessionHasNoErrors();
    expect(Password::broker('central')->tokenExists($staff, $old))->toBeFalse();
    Notification::assertSentTo(User::findOrFail($staff->id), AccountInvitation::class);
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
        ...staffTestProfile($staff), 'email' => 'changed-staff@example.test', 'last_name' => 'Changed', 'phone' => '0779107446',
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

test('staff service actions require their own permission even when the generic user action is allowed', function (string $action): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('generic-user-'.$action, [
        'saas.users.'.$action => 9999, 'saas.users.view' => 9999,
    ]));
    $staff = staffTestAccount();
    expect(Gate::forUser($actor)->allows($action, $staff))->toBeTrue();
    $service = app(StaffManagementService::class);

    expect(fn () => $action === 'suspend'
        ? $service->changeStatus($actor, $staff, StatusEnum::SUSPENDED)
        : $service->archive($actor, $staff))->toThrow(AuthorizationException::class);

    expect($staff->fresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and($staff->fresh()->trashed())->toBeFalse();
    expect(Activity::forSubject($staff)->whereIn('event', ['central_account_status_updated', 'central_account_archived'])->count())->toBe(0);
})->with(['suspend', 'archive']);

test('role replacement keeps both audit events inside its single transaction', function (): void {
    $staff = staffTestAccount();
    $role = Role::findByName('user-manager', 'central');

    app(CentralRoleAssignmentService::class)->replace($this->admin, $staff, [$role->uuid]);

    expect($staff->fresh()->hasRole($role))->toBeTrue();
    foreach (['central_roles_updated', 'central_staff_roles_replaced'] as $event) {
        $entry = Activity::forSubject($staff)->where('event', $event)->sole();
        expect($entry->causer_id)->toBe($this->admin->id);
    }
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
    Notification::assertSentTo(User::findOrFail($staff->id), AccountInvitation::class);
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
