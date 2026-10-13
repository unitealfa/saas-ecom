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

test('catalogue consultation searches codes and labels and paginates with the search preserved', function (): void {
    module04Admin();
    $viewer = module04Actor('saas.permissions.view');
    $this->actingAs($viewer, 'central')->get(route('central.permissions.index'))->assertOk()
        ->assertSee('Catalogue des permissions')->assertSee('Pays')->assertDontSee('Créer une permission')
        ->assertViewHas('permissions', fn ($permissions): bool => $permissions->count() === 20 && $permissions->total() > 20);
    $this->get(route('central.permissions.index', ['search' => 'saas.users.']))->assertOk()
        ->assertSee('saas.users.view')->assertDontSee('saas.countries.delete')
        ->assertViewHas('permissions', fn ($permissions): bool => $permissions->total() === 13);
    $this->get(route('central.permissions.index', ['search' => 'dossiers professionnels']))->assertOk()
        ->assertSee('saas.users.legal.view')->assertDontSee('saas.users.create');
    $this->get(route('central.permissions.index', ['search' => 'saas.']))->assertOk()
        ->assertViewHas('permissions', fn ($permissions): bool => str_contains($permissions->url(2), 'search=saas.'));
    $permission = Permission::findByName('saas.users.view', 'central');
    $this->get(route('central.permissions.show', $permission))->assertOk()->assertSee('Code technique')
        ->assertDontSee('id="edit-label"', false);
    $this->put(route('central.permissions.update', $permission), ['label' => 'Forbidden'])->assertForbidden();
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'label' => $permission->label]);
});

test('an editor has the consultation needed to edit without owning a delegable viewing permission', function (): void {
    module04Admin();
    $editor = module04Actor('saas.permissions.update');
    $permission = Permission::findByName('saas.users.view', 'central');
    expect(Gate::forUser($editor)->allows('viewAny', Permission::class))->toBeTrue();
    expect(app(CentralUserAccess::class)->ownsPermission($editor, 'saas.permissions.view'))->toBeFalse();
    $this->actingAs($editor, 'central')->get(route('central.permissions.index'))->assertOk()->assertSee('Modifier');
    $this->get(route('central.permissions.show', $permission))->assertOk()->assertSee('id="edit-label"', false);
    $this->put(route('central.permissions.update', $permission), ['label' => ' Lire les comptes centraux '])
        ->assertSessionHasNoErrors()->assertRedirect(route('central.permissions.show', $permission));
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'saas.users.view', 'label' => 'Lire les comptes centraux']);
});

test('a label update preserves native role grants and dates and survives an ordinary seeder rerun', function (): void {
    $admin = module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $role = Role::findByName('user-reader', 'central');
    $member = User::factory()->create();
    $member->assignRole($role);
    $assignedAt = $member->roles()->first()->pivot->assigned_at;
    $before = $permission->only(['id', 'uuid', 'name', 'guard_name', 'feature_code']);
    $roleBefore = $role->fresh()->getAttributes();
    $this->actingAs($admin, 'central')->put(route('central.permissions.update', $permission), ['label' => 'Lecture personnalisée'])
        ->assertSessionHasNoErrors();
    expect($permission->fresh()->only(array_keys($before)))->toBe($before);
    expect($role->fresh()->getAttributes())->toBe($roleBefore);
    expect($role->permissions()->first()->pivot->duration_days)->toBe(9999);
    expect($member->roles()->first()->pivot->assigned_at)->toEqual($assignedAt);
    expect($member->fresh()->checkPermissionTo('saas.users.view', 'central'))->toBeTrue();
    $audit = Activity::where('event', 'permission_label_updated')->firstOrFail();
    expect($audit->causer_id)->toBe($admin->id)->and($audit->properties['before']['label'])->toBe('Consulter les utilisateurs')
        ->and($audit->properties['after']['label'])->toBe('Lecture personnalisée');
    $this->seed(AuthorizationSeeder::class);
    $this->assertDatabaseHas('permissions', [...$before, 'label' => 'Lecture personnalisée']);
    $this->get(route('central.roles.create'))->assertOk()->assertSee('Lecture personnalisée');
    $this->put(route('central.permissions.update', $permission), ['label' => 'Lecture personnalisée'])->assertSessionHasNoErrors();
    expect(Activity::where('event', 'permission_label_updated')->count())->toBe(1);
});

test('descriptive labels are escaped and searches treat wildcard and SQL text literally', function (): void {
    $admin = module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $label = '<script>alert("catalogue")</script>';
    $this->actingAs($admin, 'central')->put(route('central.permissions.update', $permission), ['label' => $label])->assertSessionHasNoErrors();
    $this->get(route('central.permissions.show', $permission))->assertOk()->assertSee($label)->assertDontSee($label, false);
    foreach (['%', '_', "' OR 1=1 --"] as $search) {
        $this->get(route('central.permissions.index', ['search' => $search]))->assertOk()
            ->assertViewHas('permissions', fn ($permissions): bool => $permissions->total() === 0);
    }
    $this->get(route('central.permissions.index', ['search' => $label]))->assertOk()->assertSee($label)->assertDontSee($label, false)
        ->assertViewHas('permissions', fn ($permissions): bool => $permissions->total() === 1);
});

test('invalid labels searches and technical field tampering never change the permission', function (): void {
    $admin = module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $before = $permission->fresh()->getAttributes();
    $this->actingAs($admin, 'central');
    foreach (['   ', str_repeat('x', 256), ['invalid']] as $label) {
        $this->put(route('central.permissions.update', $permission), ['label' => $label])->assertSessionHasErrors('label');
    }
    $this->put(route('central.permissions.update', $permission), [
        'label' => 'Forged', 'id' => 999, 'uuid' => (string) Str::uuid(), 'name' => 'saas.fake.action',
        'guard_name' => 'tenant', 'feature_code' => 'fake', 'roles' => ['fake'], 'permissions' => ['fake'], 'durations' => [9999],
    ])->assertSessionHasErrors(['id', 'uuid', 'name', 'guard_name', 'feature_code', 'roles', 'permissions', 'durations']);
    $this->get(route('central.permissions.index', ['search' => ['invalid']]))->assertSessionHasErrors('search');
    expect($permission->fresh()->getAttributes())->toBe($before);
    expect(Activity::where('event', 'permission_label_updated')->exists())->toBeFalse();
});

test('an expired catalogue permission refuses consultation and editing at the exact expiration', function (): void {
    $this->freezeTime();
    module04Admin();
    $editor = module04Actor('saas.permissions.update', 1);
    $permission = Permission::findByName('saas.users.view', 'central');
    $this->travel(1)->days();
    $this->actingAs($editor, 'central')->get(route('central.permissions.index'))->assertForbidden();
    $this->get(route('central.permissions.show', $permission))->assertForbidden();
    $this->put(route('central.permissions.update', $permission), ['label' => 'Too late'])->assertForbidden();
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'label' => $permission->label]);
});

test('guests unverified accounts and merchants cannot access the central catalogue', function (): void {
    module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $this->get(route('central.permissions.index'))->assertRedirect(route('login'));
    $merchant = User::factory()->create();
    $this->actingAs($merchant, 'central')->get(route('central.permissions.index'))->assertForbidden();
    $this->put(route('central.permissions.update', $permission), ['label' => 'Forbidden'])->assertForbidden();
    $viewer = module04Actor('saas.permissions.view');
    $viewer->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($viewer, 'central')->get(route('central.permissions.index'))->assertRedirect(route('verification.notice'));
});

test('foreign guards and tenant contexts cannot mutate central permission labels', function (): void {
    $admin = module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $foreign = clone $permission;
    $foreign->forceFill(['guard_name' => 'tenant']);
    $gate = Gate::forUser($admin);
    expect($gate->allows('view', $foreign))->toBeFalse()->and($gate->allows('update', $foreign))->toBeFalse();
    expect(fn () => app(PermissionCatalogService::class)->updateLabel($admin, $foreign, 'Forbidden'))
        ->toThrow(AuthorizationException::class);
    tenancy()->initialized = true;
    try {
        expect($gate->allows('viewAny', Permission::class))->toBeFalse();
        expect(fn () => app(PermissionCatalogService::class)->updateLabel($admin, $permission, 'Forbidden'))
            ->toThrow(AuthorizationException::class);
    } finally {
        tenancy()->initialized = false;
    }
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'label' => $permission->label]);
});

test('technical permission creation and deletion are unavailable through the catalogue', function (): void {
    $admin = module04Admin();
    $permission = Permission::findByName('saas.users.view', 'central');
    $this->actingAs($admin, 'central')->post(route('central.permissions.index'), ['name' => 'saas.fake.action'])->assertMethodNotAllowed();
    $this->delete(route('central.permissions.show', $permission))->assertMethodNotAllowed();
    $this->get(route('central.permissions.show', ['permission' => (string) Str::uuid()]))->assertNotFound();
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'uuid' => $permission->uuid]);
    $this->assertDatabaseMissing('permissions', ['name' => 'saas.fake.action']);
});
