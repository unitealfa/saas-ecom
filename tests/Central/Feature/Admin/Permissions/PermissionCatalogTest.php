<?php

use App\Models\Central\Activity;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\User;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Authorization\PermissionCatalogService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

function module04Admin(): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));

    return $actor;
}

function module04Actor(string $permission, int $days = 9999): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('catalogue-actor', [$permission => $days]));

    return $actor;
}

test('catalogue consultation searches codes and labels and paginates without row actions', function (): void {
    module04Admin();
    $viewer = module04Actor('saas.permissions.view');
    $this->actingAs($viewer, 'central')->get(route('central.permissions.index'))->assertOk()
        ->assertSee('Catalogue des permissions')->assertSee('Pays')->assertDontSee('Créer une permission')
        ->assertDontSee(route('central.roles.create'), false)
        ->assertDontSee('Actions</th>', false)->assertDontSee('#edit-label', false)->assertDontSee('name="label"', false)
        ->assertViewHas('permissions', fn ($permissions): bool => $permissions->count() === 20 && $permissions->total() > 20);
    $this->get(route('central.permissions.index', ['search' => 'saas.users.']))->assertOk()
        ->assertSee('saas.users.view')->assertDontSee('saas.countries.delete')
        ->assertViewHas('permissions', fn ($permissions): bool => $permissions->total() === 13);
    $this->get(route('central.permissions.index', ['search' => 'dossiers professionnels']))->assertOk()
        ->assertSee('saas.users.legal.view')->assertDontSee('saas.users.create');
    $this->get(route('central.permissions.index', ['search' => 'saas.']))->assertOk()
        ->assertViewHas('permissions', fn ($permissions): bool => str_contains($permissions->url(2), 'search=saas.'));
});

test('catalogue viewers cannot modify permissions even by a direct URL', function (): void {
    module04Admin();
    $viewer = module04Actor('saas.permissions.view');
    $permission = Permission::findByName('saas.users.view', 'central');
    expect(Gate::forUser($viewer)->allows('viewAny', Permission::class))->toBeTrue()
        ->and(Gate::forUser($viewer)->allows('update', $permission))->toBeFalse();
    expect(app(CentralUserAccess::class)->ownsPermission($viewer, 'saas.permissions.view'))->toBeTrue();
    $this->actingAs($viewer, 'central')->get(route('central.permissions.index'))->assertOk()
        ->assertDontSee('Actions</th>', false)->assertDontSee('#edit-label', false);
    $this->get('/admin/permissions/'.$permission->uuid)->assertNotFound();
    $this->put('/admin/permissions/'.$permission->uuid, ['label' => 'Forbidden'])->assertNotFound();
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'label' => $permission->label]);
});

test('catalogue reads and ordinary seeding preserve labels native role grants and assignment dates', function (): void {
    $admin = module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $permission->forceFill(['label' => 'Lecture personnalisée'])->save();
    $role = Role::findByName('user-reader', 'central');
    $member = User::factory()->create();
    $member->assignRole($role);
    $assignedAt = $member->roles()->first()->pivot->assigned_at;
    $before = $permission->fresh()->getAttributes();
    $roleBefore = $role->fresh()->getAttributes();
    $activityCount = Activity::forSubject($permission)->count();
    $this->actingAs($admin, 'central')->get(route('central.permissions.index', ['search' => 'saas.users.view']))->assertOk()->assertSee('Lecture personnalisée');
    $this->get(route('central.permissions.index'))->assertOk()->assertSee(route('central.roles.create'), false);
    $this->seed(AuthorizationSeeder::class);
    expect($permission->fresh()->getAttributes())->toBe($before);
    expect($role->fresh()->getAttributes())->toBe($roleBefore);
    expect($role->permissions()->first()->pivot->duration_days)->toBe(9999);
    expect($member->roles()->first()->pivot->assigned_at)->toEqual($assignedAt);
    expect($member->fresh()->checkPermissionTo('saas.users.view', 'central'))->toBeTrue();
    $this->get(route('central.roles.create'))->assertOk()->assertSee('Lecture personnalisée');
    expect(Activity::forSubject($permission)->count())->toBe($activityCount);
});

test('descriptive labels are escaped and searches treat wildcard and SQL text literally', function (): void {
    $admin = module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $label = '<script>alert("catalogue")</script>';
    $permission->forceFill(['label' => $label])->save();
    $this->actingAs($admin, 'central');
    foreach (['%', '_', "' OR 1=1 --"] as $search) {
        $this->get(route('central.permissions.index', ['search' => $search]))->assertOk()
            ->assertViewHas('permissions', fn ($permissions): bool => $permissions->total() === 0);
    }
    $this->get(route('central.permissions.index', ['search' => $label]))->assertOk()->assertSee($label)->assertDontSee($label, false)
        ->assertViewHas('permissions', fn ($permissions): bool => $permissions->total() === 1);
});

test('invalid searches and direct technical field tampering never change permissions', function (): void {
    $admin = module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $before = $permission->fresh()->getAttributes();
    $activityCount = Activity::forSubject($permission)->count();
    $this->actingAs($admin, 'central');
    $this->put('/admin/permissions/'.$permission->uuid, [
        'label' => 'Forged', 'id' => 999, 'uuid' => (string) Str::uuid(), 'name' => 'saas.fake.action',
        'guard_name' => 'tenant', 'feature_code' => 'fake', 'roles' => ['fake'], 'permissions' => ['fake'], 'durations' => [9999],
    ])->assertNotFound();
    $this->get(route('central.permissions.index', ['search' => ['invalid']]))->assertSessionHasErrors('search');
    expect($permission->fresh()->getAttributes())->toBe($before);
    expect(Activity::forSubject($permission)->count())->toBe($activityCount);
});

test('an expired catalogue permission refuses consultation at the exact expiration', function (): void {
    $this->freezeTime();
    module04Admin();
    $viewer = module04Actor('saas.permissions.view', 1);
    $this->travel(1)->days();
    $this->actingAs($viewer, 'central')->get(route('central.permissions.index'))->assertForbidden();
});

test('guests unverified accounts and merchants cannot access the central catalogue', function (): void {
    module04Admin();
    $this->get(route('central.permissions.index'))->assertRedirect(route('login'));
    $merchant = User::factory()->create();
    $this->actingAs($merchant, 'central')->get(route('central.permissions.index'))->assertForbidden();
    $viewer = module04Actor('saas.permissions.view');
    $viewer->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($viewer, 'central')->get(route('central.permissions.index'))->assertRedirect(route('verification.notice'));
});

test('foreign guards and tenant contexts cannot consult the central catalogue and all label editing is denied', function (): void {
    $admin = module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $foreign = clone $permission;
    $foreign->forceFill(['guard_name' => 'tenant']);
    $gate = Gate::forUser($admin);
    expect($gate->allows('view', $foreign))->toBeFalse()->and($gate->allows('update', $foreign))->toBeFalse()
        ->and($gate->allows('update', $permission))->toBeFalse();
    tenancy()->initialized = true;
    try {
        expect($gate->allows('viewAny', Permission::class))->toBeFalse();
        expect(fn () => app(PermissionCatalogService::class)->paginate($admin))->toThrow(AuthorizationException::class);
    } finally {
        tenancy()->initialized = false;
    }
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'label' => $permission->label]);
});

test('technical permission creation deletion and detail endpoints are unavailable through the catalogue', function (): void {
    $admin = module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $this->actingAs($admin, 'central')->post(route('central.permissions.index'), ['name' => 'saas.fake.action'])->assertMethodNotAllowed();
    $this->delete('/admin/permissions/'.$permission->uuid)->assertNotFound();
    $this->get('/admin/permissions/'.Str::uuid())->assertNotFound();
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'uuid' => $permission->uuid]);
    $this->assertDatabaseMissing('permissions', ['name' => 'saas.fake.action']);
});
