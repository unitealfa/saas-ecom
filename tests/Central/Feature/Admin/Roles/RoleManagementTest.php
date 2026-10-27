<?php

use App\Models\Central\Audit\Activity;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\User;
use App\Services\Central\Authorization\CentralUserAccess;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

function module03Admin(): User
{
    test()->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));

    return $admin;
}

test('controller corrections prepare owned permissions with bounded queries and respect revocations and expiry', function (): void {
    $this->freezeTime();
    $admin = module03Admin();
    $actor = module03Actor(['saas.roles.create' => 9999, 'saas.users.view' => 1, 'saas.countries.view' => 9999]);
    $access = app(CentralUserAccess::class);
    $connection = DB::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();
    try {
        $owned = $access->ownedPermissions($actor)->pluck('name')->sort()->values()->all();
        $queryCount = count($connection->getQueryLog());
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }

    expect($owned)->toBe(collect(['saas.roles.create', 'saas.users.view', 'saas.countries.view'])->sort()->values()->all())
        ->and($queryCount)->toBeLessThanOrEqual(5);
    $actor->revokePermissionTo('saas.countries.view');
    $this->travel(1)->days();
    expect($access->ownedPermissions($actor)->pluck('name')->all())->toBe(['saas.roles.create']);
    expect($access->ownedPermissions($admin)->count())->toBe(Permission::forCentralGuard()->count());
    $this->actingAs($actor, 'central')->get(route('central.roles.create'))->assertOk()
        ->assertViewHas('permissions', fn ($permissions): bool => $permissions->pluck('name')->all() === ['saas.roles.create']);
});

test('controller corrections redirect role writers without listing access to the dashboard', function (): void {
    module03Admin();
    $actor = module03Actor(['saas.roles.create' => 9999, 'saas.users.view' => 9999]);
    $this->actingAs($actor, 'central')->post(route('central.roles.store'), module03Payload('New role', ['saas.users.view' => 10]))
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $this->get(route('central.roles.index'))->assertForbidden();
});

/** @param array<string, int> $grants */
function module03Actor(array $grants): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('module03-delegator', $grants));

    return $actor;
}

/** @param array<string, int> $grants */
function module03Payload(string $name, array $grants): array
{
    $permissions = Permission::where('guard_name', 'central')->whereIn('name', array_keys($grants))->get();

    return ['name' => $name, 'label' => $name, 'permissions' => $permissions->pluck('uuid')->all(),
        'durations' => $permissions->mapWithKeys(fn (Permission $permission): array => [$permission->uuid => $grants[$permission->name]])->all()];
}

test('each role action is independent from the explicit list permission and other mutations', function (string $permission, string $ability): void {
    module03Admin();
    $actor = module03Actor([$permission => 9999, 'saas.users.view' => 9999]);
    $role = Role::createWithPermissions('consulted-role', ['saas.users.view' => 12]);
    $gate = Gate::forUser($actor);
    expect($gate->allows('viewAny', Role::class))->toBe($permission === 'saas.roles.view');
    expect($gate->allows($ability, $ability === 'create' ? Role::class : $role))->toBeTrue();
    foreach (['update', 'updatePermissions', 'updateDurations', 'delete'] as $other) {
        if ($other !== $ability) {
            expect($gate->allows($other, $role))->toBeFalse();
        }
    }
})->with([
    ['saas.roles.view', 'view'], ['saas.roles.create', 'create'], ['saas.roles.update', 'update'],
    ['saas.roles.delete', 'delete'], ['saas.roles.permissions.view', 'viewPermissions'],
    ['saas.roles.permissions.update', 'updatePermissions'], ['saas.roles.durations.update', 'updateDurations'],
    ['saas.roles.assignments.view', 'viewAssignments'],
]);

test('role consultation hides compositions and assignments without their own permissions', function (): void {
    module03Admin();
    $role = Role::createWithPermissions('private-composition', ['saas.countries.delete' => 42]);
    $member = User::factory()->create();
    $member->assignRole($role);
    $viewer = module03Actor(['saas.roles.view' => 9999]);
    $this->actingAs($viewer, 'central')->get(route('central.roles.index'))->assertOk()
        ->assertSee('private-composition')->assertDontSee('Retirer des pays')->assertDontSee('Créer un rôle');
    $this->get(route('central.roles.show', $role))->assertOk()->assertDontSee('Retirer des pays')
        ->assertDontSee($member->email)->assertDontSee('Administrateurs possédant ce rôle');
    $this->get(route('central.roles.edit', $role))->assertForbidden();
    $this->delete(route('central.roles.destroy', $role))->assertForbidden();
    $this->assertDatabaseHas('roles', ['id' => $role->id]);
});

test('assignments consultation includes archived accounts without exposing their sensitive columns', function (): void {
    module03Admin();
    $role = Role::createWithPermissions('assigned-role', ['saas.users.view' => 3]);
    $member = User::factory()->create();
    $member->assignRole($role);
    $member->delete();
    $viewer = module03Actor(['saas.roles.view' => 9999, 'saas.roles.assignments.view' => 9999]);
    $this->actingAs($viewer, 'central')->get(route('central.roles.show', $role))->assertOk()
        ->assertSee($member->email)->assertSee('Archivé')->assertDontSee($member->uuid)
        ->assertDontSee($member->getRawOriginal('password'))->assertDontSee('Permissions et durées');
});

test('metadata editing preserves permissions durations identity and assignment dates', function (): void {
    module03Admin();
    $actor = module03Actor(['saas.roles.update' => 9999, 'saas.users.view' => 9999]);
    $role = Role::createWithPermissions('before-name', ['saas.users.view' => 6]);
    $member = User::factory()->create();
    $member->assignRole($role);
    $assignedAt = $member->roles()->first()->pivot->assigned_at;
    $this->actingAs($actor, 'central')->get(route('central.roles.edit', $role))->assertOk()
        ->assertSee('Nom unique du rôle')->assertDontSee('name="permissions[]"', false)->assertDontSee('name="durations[', false);
    $this->put(route('central.roles.update', $role), ['expected_version' => 1, 'name' => ' after-name ', 'label' => 'Nouveau libellé'])
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $updated = $role->fresh();
    expect($updated->name)->toBe('after-name')->and($updated->uuid)->toBe($role->uuid)
        ->and($updated->permission_signature)->toBe($role->permission_signature)->and($updated->permission_version)->toBe(2);
    expect($updated->permissionComposition()->first()->pivot->duration_days)->toBe(6);
    expect($member->roles()->first()->pivot->assigned_at)->toEqual($assignedAt);
    expect(Activity::where('event', 'role_updated')->where('subject_id', $role->id)->exists())->toBeTrue();
});

test('metadata permission cannot change composition or durations through a forged request', function (): void {
    module03Admin();
    $actor = module03Actor(['saas.roles.update' => 9999, 'saas.users.view' => 9999, 'saas.countries.view' => 9999]);
    $role = Role::createWithPermissions('metadata-only', ['saas.users.view' => 12]);
    $this->actingAs($actor, 'central')->put(route('central.roles.update', $role),
        ['expected_version' => 1, ...module03Payload('metadata-only', ['saas.users.view' => 12, 'saas.countries.view' => 9999])])->assertForbidden();
    $this->put(route('central.roles.update', $role), ['expected_version' => 1, ...module03Payload('metadata-only', ['saas.users.view' => 2])])->assertForbidden();
    expect($role->fresh()->permission_version)->toBe(1);
    expect($role->permissionComposition()->pluck('name')->all())->toBe(['saas.users.view']);
    expect($role->permissionComposition()->first()->pivot->duration_days)->toBe(12);
});

test('composition editing preserves retained durations and gives new permissions the documented default', function (): void {
    module03Admin();
    $actor = module03Actor(['saas.roles.permissions.update' => 9999, 'saas.users.view' => 9999, 'saas.countries.view' => 9999]);
    $role = Role::createWithPermissions('composition-only', ['saas.users.view' => 12]);
    $payload = module03Payload('composition-only', ['saas.users.view' => 12, 'saas.countries.view' => 9999]);
    unset($payload['name'], $payload['label'], $payload['durations']);
    $this->actingAs($actor, 'central')->get(route('central.roles.edit', $role))->assertOk()
        ->assertSee('name="permissions[]"', false)->assertDontSee('name="durations[', false);
    $this->put(route('central.roles.update', $role), ['expected_version' => 1, ...$payload])->assertSessionHasNoErrors();
    expect($role->permissionComposition()->where('name', 'saas.users.view')->first()->pivot->duration_days)->toBe(12);
    expect($role->permissionComposition()->where('name', 'saas.countries.view')->first()->pivot->duration_days)->toBe(9999);
    $payload = module03Payload('composition-only', ['saas.users.view' => 3]);
    $this->put(route('central.roles.update', $role), ['expected_version' => 2, ...$payload])->assertForbidden();
    expect($role->fresh()->permission_version)->toBe(2);
});

test('profile duration editing keeps existing individual dates and does not change their expiration', function (): void {
    $this->freezeTime();
    module03Admin();
    $actor = module03Actor(['saas.roles.durations.update' => 9999, 'saas.users.view' => 9999]);
    $role = Role::createWithPermissions('duration-only', ['saas.users.view' => 3]);
    $member = User::factory()->create();
    $member->assignRole($role);
    $assignedAt = $member->roles()->first()->pivot->assigned_at;
    $uuid = $role->permissionComposition()->first()->uuid;
    $this->travel(2)->days();
    $this->actingAs($actor, 'central')->get(route('central.roles.edit', $role))->assertOk()
        ->assertDontSee('name="permissions[]"', false)->assertSee('name="durations[', false);
    $this->put(route('central.roles.update', $role), ['expected_version' => 1, 'durations' => [$uuid => 1]])->assertSessionHasNoErrors();
    expect($member->roles()->first()->pivot->assigned_at)->toEqual($assignedAt);
    expect($member->fresh()->checkPermissionTo('saas.users.view', 'central'))->toBeTrue();
    $this->travel(1)->days();
    expect($member->fresh()->checkPermissionTo('saas.users.view', 'central'))->toBeFalse();
    expect($role->fresh()->permission_version)->toBe(2);
    $this->put(route('central.roles.update', $role), ['expected_version' => 2, 'name' => 'forbidden-rename'])->assertForbidden();
    expect($role->fresh()->name)->toBe('duration-only');
});

test('delegated role creation offers only owned permissions and refuses derived or expired rights', function (): void {
    $this->freezeTime();
    module03Admin();
    $actor = module03Actor(['saas.roles.create' => 9999, 'saas.users.create' => 9999, 'saas.countries.view' => 1]);
    $this->travel(1)->days();
    $this->actingAs($actor, 'central')->get(route('central.roles.create'))->assertOk()
        ->assertSee('Créer des utilisateurs')->assertDontSee('Consulter les utilisateurs')->assertDontSee('Consulter les pays');
    $this->post(route('central.roles.store'), module03Payload('derived-denied', ['saas.users.view' => 5]))->assertForbidden();
    $this->post(route('central.roles.store'), module03Payload('expired-denied', ['saas.countries.view' => 5]))->assertForbidden();
    $this->post(route('central.roles.store'), module03Payload('allowed-role', ['saas.users.create' => 20]))->assertSessionHasNoErrors();
    expect(Role::where('name', 'allowed-role')->first()->permissionComposition()->first()->pivot->duration_days)->toBe(20);
    $this->assertDatabaseMissing('roles', ['name' => 'derived-denied']);
    $this->assertDatabaseMissing('roles', ['name' => 'expired-denied']);
});

test('an expired module action refuses the page and mutation at its exact expiration', function (): void {
    $this->freezeTime();
    module03Admin();
    $actor = module03Actor(['saas.roles.create' => 1, 'saas.users.view' => 9999]);
    $this->travel(1)->days();
    $this->actingAs($actor, 'central')->get(route('central.roles.index'))->assertForbidden();
    $this->post(route('central.roles.store'), module03Payload('too-late', ['saas.users.view' => 7]))->assertForbidden();
    $this->assertDatabaseMissing('roles', ['name' => 'too-late']);
});

test('unused custom role deletion removes only its composition and retains an audit snapshot', function (): void {
    $admin = module03Admin();
    $role = Role::createWithPermissions('unused-role', ['saas.users.view' => 7]);
    $permission = $role->permissionComposition()->first();
    $this->actingAs($admin, 'central')->delete(route('central.roles.destroy', $role))
        ->assertSessionHasNoErrors()->assertRedirect(route('central.roles.index'));
    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'uuid' => $permission->uuid]);
    $audit = Activity::where('event', 'role_deleted')->firstOrFail();
    expect($audit->properties['before']['uuid'])->toBe($role->uuid)->and($audit->causer_id)->toBe($admin->id);
});

test('deletion refuses assignments even after their permissions expired and the account was archived', function (): void {
    $this->freezeTime();
    $admin = module03Admin();
    $role = Role::createWithPermissions('still-assigned', ['saas.users.view' => 1]);
    $member = User::factory()->create();
    $member->assignRole($role);
    $member->delete();
    $this->travel(2)->days();
    $this->actingAs($admin, 'central')->delete(route('central.roles.destroy', $role))->assertSessionHasErrors('role');
    $this->assertDatabaseHas('roles', ['id' => $role->id]);
    $this->assertDatabaseHas('model_has_roles', ['role_id' => $role->id, 'model_id' => $member->id]);
    expect(Activity::where('event', 'role_deleted')->exists())->toBeFalse();
});

test('system roles cannot be edited or deleted including the protected super admin', function (string $name): void {
    $admin = module03Admin();
    $role = Role::findByName($name, 'central');
    $this->actingAs($admin, 'central')->get(route('central.roles.edit', $role))->assertForbidden();
    $this->put(route('central.roles.update', $role), ['label' => 'Changed'])->assertForbidden();
    $this->delete(route('central.roles.destroy', $role))->assertForbidden();
    $this->assertDatabaseHas('roles', ['id' => $role->id, 'uuid' => $role->uuid, 'label' => $role->label]);
})->with(['root', 'user-manager', 'user-reader']);

test('a role from another guard is not consultable or mutable by central routes', function (): void {
    $admin = module03Admin();
    $permission = new Permission(['name' => 'foreign.read', 'label' => 'Foreign']);
    $permission->forceFill(['guard_name' => 'tenant']);
    expect(fn () => $permission->save())->toThrow(QueryException::class, 'ck_permissions_518cf8da0c');
    $role = new Role(['name' => 'foreign-role', 'label' => 'Foreign']);
    $role->forceFill(['guard_name' => 'tenant', 'permission_signature' => hash('sha256', 'foreign-composition')]);
    expect(fn () => $role->save())->toThrow(QueryException::class, 'ck_roles_518cf8da0c');

    /** Le CHECK SQL interdit ce modèle en BDD ; vérifier aussi le refus HTTP s'il arrive par un binding. */
    Route::bind('role', function (string $uuid) use ($role): Role {
        abort_unless($uuid === $role->uuid, 404);

        return $role;
    });
    $this->actingAs($admin, 'central')->get(route('central.roles.show', $role))->assertForbidden();
    $this->get(route('central.roles.edit', $role))->assertForbidden();
    $this->delete(route('central.roles.destroy', $role))->assertForbidden();
    $this->get(route('central.roles.index'))->assertOk()->assertDontSee('foreign-role');
});

test('new roles reject out of range durations empty compositions and protected metadata', function (): void {
    $admin = module03Admin();
    $this->actingAs($admin, 'central');
    foreach ([0, 10000] as $days) {
        $payload = module03Payload('invalid-duration', ['saas.users.view' => $days]);
        $this->post(route('central.roles.store'), $payload)->assertSessionHasErrors('durations.'.array_key_first($payload['durations']));
    }
    $this->post(route('central.roles.store'), ['name' => 'empty', 'label' => 'Empty', 'permissions' => []])->assertSessionHasErrors('permissions');
    $this->post(route('central.roles.store'), [...module03Payload('forged-root', ['saas.users.view' => 8]), 'is_super_admin' => true])
        ->assertSessionHasErrors('is_super_admin');
    $this->assertDatabaseMissing('roles', ['name' => 'invalid-duration']);
    $this->assertDatabaseMissing('roles', ['name' => 'forged-root']);
});

test('role duration changes reject identical compositions without changing either assignment', function (): void {
    $admin = module03Admin();
    $role = Role::createWithPermissions('first-composition', ['saas.users.view' => 4]);
    Role::createWithPermissions('second-composition', ['saas.users.view' => 8]);
    $uuid = $role->permissionComposition()->first()->uuid;
    $this->actingAs($admin, 'central')->put(route('central.roles.update', $role), ['expected_version' => 1, 'durations' => [$uuid => 8]])->assertSessionHasErrors('name');
    expect($role->permissionComposition()->first()->pivot->duration_days)->toBe(4);
    expect($role->fresh()->permission_version)->toBe(1);
});

test('profile updates do not overwrite an existing recipients direct permission', function (): void {
    $admin = module03Admin();
    $role = Role::createWithPermissions('role-before-direct-conflict', ['saas.users.view' => 4]);
    $member = User::factory()->create();
    $member->assignRole($role);
    $member->givePermissionTo('saas.countries.view');
    $payload = module03Payload('role-before-direct-conflict', ['saas.users.view' => 4, 'saas.countries.view' => 8]);
    $end = $member->permissions()->where('name', 'saas.countries.view')->first()->pivot->expires_at;
    $this->actingAs($admin, 'central')->put(route('central.roles.update', $role), ['expected_version' => 1, ...$payload])->assertSessionHasNoErrors();
    expect($role->permissionComposition()->pluck('name')->all())->toBe(['saas.users.view', 'saas.countries.view']);
    expect($role->fresh()->permission_version)->toBe(2);
    expect($member->permissions()->where('name', 'saas.countries.view')->first()->pivot->expires_at)->toEqual($end);
});

test('guests and merchants cannot access or mutate SaaS roles', function (): void {
    module03Admin();
    $role = Role::createWithPermissions('secure-role', ['saas.users.view' => 9]);
    $this->get(route('central.roles.index'))->assertRedirect(route('login'));
    $merchant = User::factory()->create();
    $this->actingAs($merchant, 'central')->get(route('central.roles.index'))->assertForbidden();
    $this->put(route('central.roles.update', $role), ['name' => 'forged'])->assertForbidden();
    $this->delete(route('central.roles.destroy', $role))->assertForbidden();
    $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'secure-role']);
});

test('role deletion refuses an existing business reference without silently deleting it', function (): void {
    $admin = module03Admin();
    $role = Role::createWithPermissions('referenced-role', ['saas.users.view' => 5]);
    $reference = (string) Str::uuid();
    DB::connection($role->getConnectionName())->table('admin_restrictions')->insert([
        'uuid' => $reference, 'admin_id' => $admin->id, 'permission_id' => $role->permissionComposition()->first()->id,
        'target_role_id' => $role->id, 'created_by_id' => $admin->id, 'effect' => 1, 'status' => 1, 'started_at' => now(),
    ]);
    $this->actingAs($admin, 'central')->delete(route('central.roles.destroy', $role))->assertSessionHasErrors('role');
    $this->assertDatabaseHas('roles', ['id' => $role->id]);
    $this->assertDatabaseHas('admin_restrictions', ['uuid' => $reference, 'target_role_id' => $role->id]);
});

test('role mutation and deletion cannot target a composition stronger than the delegator owns', function (): void {
    module03Admin();
    $actor = module03Actor(['saas.roles.update' => 9999, 'saas.roles.delete' => 9999]);
    $role = Role::createWithPermissions('stronger-role', ['saas.users.update' => 5]);
    $this->actingAs($actor, 'central')->get(route('central.roles.edit', $role))->assertForbidden();
    $this->put(route('central.roles.update', $role), ['label' => 'Forbidden'])->assertForbidden();
    $this->delete(route('central.roles.destroy', $role))->assertForbidden();
    $this->assertDatabaseHas('roles', ['id' => $role->id, 'label' => $role->label]);
});

test('duration-only updates reject unrelated permissions and retries do not bump the version', function (): void {
    $admin = module03Admin();
    $role = Role::createWithPermissions('unchanged-role', ['saas.users.view' => 12]);
    $uuid = $role->permissionComposition()->first()->uuid;
    $other = Permission::findByName('saas.users.create', 'central');
    $this->actingAs($admin, 'central')->put(route('central.roles.update', $role), ['expected_version' => 1, 'durations' => [$other->uuid => 5]])
        ->assertSessionHasErrors('durations');
    $this->put(route('central.roles.update', $role), ['expected_version' => 1, 'durations' => [$uuid => 12]])->assertSessionHasNoErrors();
    expect($role->fresh()->permission_version)->toBe(1);
    expect(Activity::where('event', 'role_updated')->where('subject_id', $role->id)->exists())->toBeFalse();
});

test('role catalogue seeding preserves historical permission identities and existing assignments', function (): void {
    module03Admin();
    $historical = Permission::whereIn('name', ['saas.users.view', 'saas.users.create', 'saas.users.update', 'saas.staff.invite', 'saas.users.roles.assign'])
        ->orderBy('id')->get(['id', 'uuid', 'name'])->toArray();
    $role = Role::findByName('user-reader', 'central');
    $member = User::factory()->create();
    $member->assignRole($role);
    $assignedAt = $member->roles()->first()->pivot->assigned_at;
    $this->seed(AuthorizationSeeder::class);
    expect(Permission::whereIn('name', array_column($historical, 'name'))->orderBy('id')->get(['id', 'uuid', 'name'])->toArray())->toBe($historical);
    expect(Permission::where('guard_name', 'central')->where('name', 'like', 'saas.roles.%')->count())->toBe(8);
    expect($member->roles()->first()->pivot->assigned_at)->toEqual($assignedAt);
    expect($role->fresh()->permissionComposition()->pluck('name')->all())->toBe(['saas.users.view']);
});

test('an obsolete role form cannot overwrite identity composition or durations', function (): void {
    $admin = module03Admin();
    $role = Role::createWithPermissions('concurrent-role', ['saas.users.view' => 17]);
    $member = User::factory()->create();
    $member->assignRole($role);
    $assignedAt = $member->roles()->first()->pivot->assigned_at;
    $url = route('central.roles.update', $role);
    $this->actingAs($admin, 'central')->get(route('central.roles.edit', $role))
        ->assertOk()->assertSee('name="expected_version" value="1"', false);
    $this->put($url, ['expected_version' => 1, 'label' => 'Latest label'])->assertSessionHasNoErrors();
    $this->put($url, ['expected_version' => 1, ...module03Payload('stale-name', ['saas.users.view' => 8])])
        ->assertSessionHasErrors('expected_version');
    $this->put($url, ['label' => 'Missing version'])->assertSessionHasErrors('expected_version');
    expect($role->fresh()->label)->toBe('Latest label')
        ->and($role->fresh()->name)->toBe('concurrent-role')
        ->and($role->fresh()->permission_version)->toBe(2)
        ->and($role->permissionComposition()->first()->pivot->duration_days)->toBe(17)
        ->and($member->roles()->first()->pivot->assigned_at)->toEqual($assignedAt)
        ->and(Activity::forSubject($role)->where('event', 'role_updated')->count())->toBe(1);
});

test('role forms accept uppercase permission uuids without losing their durations', function (): void {
    $admin = module03Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $uuid = strtoupper($permission->uuid);

    $this->actingAs($admin, 'central')->post(route('central.roles.store'), [
        'name' => 'uppercase-permissions', 'label' => 'Uppercase permissions',
        'permissions' => [$uuid], 'durations' => [$uuid => 23],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $role = Role::findByName('uppercase-permissions', 'central');
    expect($role->permissionComposition()->first()->pivot->duration_days)->toBe(23);

    $this->put(route('central.roles.update', $role), [
        'expected_version' => 1, 'durations' => [$uuid => 31],
    ])->assertSessionHasNoErrors();
    expect($role->permissionComposition()->first()->pivot->duration_days)->toBe(31);

    $this->put(route('central.roles.update', $role), [
        'expected_version' => 2, 'permissions' => [$uuid],
    ])->assertSessionHasNoErrors();
    expect($role->permissionComposition()->first()->pivot->duration_days)->toBe(31)
        ->and($role->fresh()->permission_version)->toBe(2);
});

test('role forms reject a permission repeated with different uuid casing', function (): void {
    $admin = module03Admin();
    $permission = Permission::findByName('saas.users.view', 'central');

    $this->actingAs($admin, 'central')->post(route('central.roles.store'), [
        'name' => 'duplicate-casing', 'label' => 'Duplicate casing',
        'permissions' => [$permission->uuid, strtoupper($permission->uuid)],
    ])->assertSessionHasErrors('permissions.0');

    expect(Role::where('name', 'duplicate-casing')->exists())->toBeFalse();
});
