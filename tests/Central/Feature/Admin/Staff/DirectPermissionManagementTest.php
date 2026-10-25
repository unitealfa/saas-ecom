<?php

use App\Models\Central\Activity;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Authorization\CentralPermissionAssignmentService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->freezeTime();
    $this->seed(AuthorizationSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
});

function individualGrant(User $user, string $code, bool $enabled, int $days = 9999, bool $renew = false): array
{
    return [
        'expected_state' => app(CentralPermissionAssignmentService::class)->version($user->fresh()),
        'grants' => [['permission' => Permission::findByName($code, 'central')->uuid,
            'enabled' => $enabled, 'days' => $days, 'renew' => $renew]],
    ];
}

test('a shared profile gives direct rights and an individual revocation changes only Karim', function (): void {
    $role = Role::findByName('user-manager', 'central');
    [$ahmed, $mohamed, $karim] = User::factory()->count(3)->create()->all();
    foreach ([$ahmed, $mohamed, $karim] as $member) {
        $member->assignRole($role);
        expect($member->can('saas.users.update'))->toBeTrue();
    }
    expect($role->permissions()->count())->toBe(3);
    $dates = $ahmed->permissions()->first()->pivot->expires_at;
    $this->actingAs($this->admin, 'central')->put(route('central.staff.permissions.update', $karim),
        individualGrant($karim, 'saas.users.update', false))->assertSessionHasNoErrors();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($karim->fresh()->can('saas.users.update'))->toBeFalse()
        ->and($karim->fresh()->hasRole($role))->toBeTrue()
        ->and($karim->fresh()->can('saas.users.view'))->toBeTrue()
        ->and($karim->fresh()->can('saas.users.create'))->toBeTrue()
        ->and($ahmed->fresh()->can('saas.users.update'))->toBeTrue()
        ->and($mohamed->fresh()->can('saas.users.update'))->toBeTrue()
        ->and($ahmed->permissions()->first()->pivot->expires_at)->toEqual($dates);
    $karim->syncRoles($role);
    expect($karim->fresh()->can('saas.users.update'))->toBeFalse();
    $this->put(route('central.staff.permissions.update', $karim), individualGrant($karim, 'saas.users.update', true, 12))
        ->assertSessionHasNoErrors();
    expect($karim->fresh()->can('saas.users.update'))->toBeTrue()
        ->and(Activity::forSubject($karim)->where('event', 'central_staff_permissions_updated')->count())->toBe(2);
});

test('view permission is independently revocable and modification no longer recreates consultation', function (): void {
    $staff = User::factory()->create();
    $staff->assignRole(Role::findByName('user-manager', 'central'));
    $this->actingAs($this->admin, 'central')->put(route('central.staff.permissions.update', $staff),
        individualGrant($staff, 'saas.users.view', false))->assertSessionHasNoErrors();
    expect($staff->fresh()->can('saas.users.update'))->toBeTrue()->and($staff->fresh()->can('saas.users.view'))->toBeFalse();
    $this->actingAs($staff, 'central')->get(route('central.users.index'))->assertForbidden();
});

test('saving kept permissions preserves dates and expired rights require explicit renewal', function (): void {
    $role = Role::createWithPermissions('short-profile', ['saas.users.view' => 2]);
    $staff = User::factory()->create();
    $staff->assignRole($role);
    $before = $staff->permissions()->first()->pivot->expires_at;
    $this->travel(3)->days();
    $this->actingAs($this->admin, 'central')->put(route('central.staff.permissions.update', $staff),
        individualGrant($staff, 'saas.users.view', true, 2))->assertSessionHasNoErrors();
    expect($staff->fresh()->can('saas.users.view'))->toBeFalse()->and($staff->permissions()->first()->pivot->expires_at)->toEqual($before);
    $payload = individualGrant($staff, 'saas.users.view', true, 4, true);
    $this->put(route('central.staff.permissions.update', $staff), $payload)->assertSessionHasErrors('reason');
    $this->put(route('central.staff.permissions.update', $staff), [...$payload, 'reason' => 'Renouvellement approuvé'])
        ->assertSessionHasNoErrors();
    expect($staff->fresh()->can('saas.users.view'))->toBeTrue();
});

test('obsolete permission forms cannot overwrite a more recent grant or leave partial changes', function (): void {
    $staff = User::factory()->create();
    $staff->assignRole(Role::findByName('user-reader', 'central'));
    $stale = individualGrant($staff, 'saas.users.view', false);
    $this->actingAs($this->admin, 'central')->put(route('central.staff.permissions.update', $staff),
        individualGrant($staff, 'saas.users.create', true, 5))->assertSessionHasNoErrors();
    $this->put(route('central.staff.permissions.update', $staff), $stale)->assertSessionHasErrors('expected_state');
    expect($staff->fresh()->can('saas.users.view'))->toBeTrue()->and($staff->fresh()->can('saas.users.create'))->toBeTrue();
});

test('root self and merchant targets are protected from permission edits', function (string $kind): void {
    $actor = $this->admin;
    $target = $kind === 'root' ? $this->admin : User::factory()->create();
    if ($kind === 'self') {
        $target->assignRole(Role::createWithPermissions('self-editor', ['saas.staff.permissions.update' => 9999, 'saas.users.view' => 9999]));
        $actor = $target;
    }
    $this->actingAs($actor, 'central')->put(route('central.staff.permissions.update', $target),
        individualGrant($target, 'saas.users.view', false))->assertForbidden();
})->with(['root', 'self', 'merchant']);

test('read-only inspectors cannot edit and the interface hides controls', function (): void {
    $inspector = User::factory()->create();
    $inspector->assignRole(Role::createWithPermissions('inspector', ['saas.staff.permissions.view' => 9999]));
    $staff = User::factory()->create();
    $staff->assignRole(Role::findByName('user-reader', 'central'));
    $this->actingAs($inspector, 'central')->get(route('central.staff.permissions.index', $staff))->assertOk()
        ->assertDontSee('Enregistrer les permissions');
    $this->put(route('central.staff.permissions.update', $staff), individualGrant($staff, 'saas.users.view', false))->assertForbidden();
    $this->actingAs($this->admin, 'central')->get(route('central.staff.permissions.index', $staff))->assertOk()
        ->assertSee('Enregistrer les permissions')->assertSee('saas.countries.update');
});

test('a delegator cannot add an unowned permission and a failed batch rolls back', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('limited-editor', ['saas.staff.permissions.update' => 9999, 'saas.users.view' => 9999]));
    $staff = User::factory()->create();
    $staff->assignRole(Role::findByName('user-reader', 'central'));
    $payload = individualGrant($staff, 'saas.users.view', false);
    $payload['grants'][] = individualGrant($staff, 'saas.users.archive', true)['grants'][0];
    $this->actingAs($actor, 'central')->get(route('central.staff.permissions.index', $staff))->assertOk()
        ->assertDontSee('saas.users.archive');
    $this->put(route('central.staff.permissions.update', $staff), $payload)->assertForbidden();
    expect($staff->fresh()->can('saas.users.view'))->toBeTrue()->and($staff->fresh()->can('saas.users.archive'))->toBeFalse()
        ->and(Activity::forSubject($staff)->where('event', 'central_staff_permissions_updated')->count())->toBe(0);
});

test('delegation policy checks effective ownership guard and catalogue even for root', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('expiring-delegation', ['saas.users.view' => 1]));
    $permission = Permission::findByName('saas.users.view', 'central');
    $legacy = Permission::create(['name' => 'saas.legacy.read', 'label' => 'Historique']);
    $foreign = (new Permission)->forceFill(['name' => 'saas.users.view', 'guard_name' => 'tenant']);

    expect(Gate::forUser($actor)->allows('delegate', $permission))->toBeTrue();
    expect(Gate::forUser($this->admin)->allows('delegate', $foreign))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('delegate', $legacy))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('update', $permission))->toBeFalse();
    $this->travel(1)->days();
    expect(Gate::forUser($actor)->allows('delegate', $permission))->toBeFalse();
});

test('changing profile composition affects future assignments without changing existing direct rights', function (): void {
    $profile = Role::createWithPermissions('editable-profile', ['saas.users.view' => 3]);
    $member = User::factory()->create();
    $member->assignRole($profile);
    $end = $member->permissions()->first()->pivot->expires_at;
    $profile->givePermissionTo('saas.users.update');
    $profile->setPermissionDuration('saas.users.view', 20);
    $new = User::factory()->create();
    $new->assignRole($profile->fresh());
    expect($member->fresh()->can('saas.users.update'))->toBeFalse()
        ->and($member->permissions()->first()->pivot->expires_at)->toEqual($end)
        ->and($new->fresh()->can('saas.users.update'))->toBeTrue();
});

test('expired permission administration cannot delegate despite a cached package catalogue', function (): void {
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('expiring-administrator', [
        'saas.staff.permissions.update' => 1, 'saas.users.view' => 9999,
    ]));
    $staff = User::factory()->create();
    $staff->assignRole(Role::findByName('user-reader', 'central'));
    expect($actor->can('saas.staff.permissions.update'))->toBeTrue();
    $this->travel(1)->days();
    $this->actingAs($actor, 'central')->put(route('central.staff.permissions.update', $staff),
        individualGrant($staff, 'saas.users.view', false))->assertForbidden();
    expect($staff->fresh()->can('saas.users.view'))->toBeTrue();
});

test('rollback clears a package catalogue populated with an uncommitted permission', function (): void {
    try {
        app(AuthorizationMutation::class)->transaction(function (): void {
            Permission::create(['name' => 'saas.test.transient', 'label' => 'Transient']);
            expect($this->admin->can('saas.test.transient'))->toBeTrue();
            throw new RuntimeException('Rollback the catalogue');
        });
        $this->fail('Rollback was expected.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Rollback the catalogue');
    }
    expect(Permission::where('name', 'saas.test.transient')->exists())->toBeFalse()
        ->and($this->admin->fresh()->can('saas.test.transient'))->toBeFalse();
});

test('authorization refusal also clears permissions cached inside a rolled back transaction', function (): void {
    $actor = User::factory()->create();
    $code = 'saas.services.rollback';

    expect(fn () => app(AuthorizationMutation::class)->transaction(function () use ($actor, $code): void {
        Permission::create(['name' => $code, 'label' => 'Transaction annulée']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findByName($code, 'central');
        Gate::forUser($actor)->authorize('create', App\Models\Central\User::class);
    }))->toThrow(AuthorizationException::class);

    expect(Permission::where('name', $code)->exists())->toBeFalse();
    expect(fn () => Permission::findByName($code, 'central'))
        ->toThrow(PermissionDoesNotExist::class);
});

test('service audit refuses a package role without the configured authorization metadata', function (): void {
    $staff = User::factory()->create();
    $role = Role::findByName('user-reader', 'central');
    $packageRole = Spatie\Permission\Models\Role::findOrFail($role->id);

    expect(fn () => $staff->assignRole($packageRole))->toThrow(LogicException::class);
    expect($staff->roles()->count())->toBe(0);
    $staff->assignRole($role);
    expect($staff->fresh()->can('saas.users.view'))->toBeTrue();
});

test('consolidation preserves role definitions individual grants and dates with a private backup', function (): void {
    Storage::fake('local');
    $member = User::factory()->create();
    $role = Role::findByName('user-manager', 'central');
    $member->assignRole($role);
    $member->revokePermissionTo('saas.users.update');
    $snapshot = [];
    foreach (['roles', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'] as $table) {
        $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
    }
    DB::connection()->commit();
    (require database_path('migrations/central/2026_10_21_000000_create_role_permission_templates_table.php'))->up();
    DB::table('role_permission_templates')->insert($snapshot['role_has_permissions']);
    DB::table('role_has_permissions')->delete();
    $migration = require database_path('migrations/central/2026_10_21_051638_consolidate_central_role_permissions.php');

    $migration->up();

    expect(Schema::hasTable('role_permission_templates'))->toBeFalse();
    foreach ($snapshot as $table => $rows) {
        expect(DB::table($table)->get()->map(fn ($row) => (array) $row)->all())->toBe($rows);
    }
    expect($member->fresh()->can('saas.users.update'))->toBeFalse()
        ->and($member->fresh()->can('saas.users.view'))->toBeTrue()
        ->and($member->fresh()->getAllPermissions()->pluck('name'))->not->toContain('saas.users.update');
    $files = Storage::disk('local')->files('authorization-transitions');
    expect($files)->toHaveCount(1);
    $backup = json_decode(Crypt::decryptString(Storage::disk('local')->get($files[0])), true, flags: JSON_THROW_ON_ERROR);
    $sourceTriples = collect($backup['role_permission_templates'])->map(fn ($row) => [$row['role_id'], $row['permission_id'], $row['duration_days']])->sort()->values()->all();
    $expectedTriples = collect($snapshot['role_has_permissions'])->map(fn ($row) => [$row['role_id'], $row['permission_id'], $row['duration_days']])->sort()->values()->all();
    expect($sourceTriples)->toBe($expectedTriples);
    $migration->up();
    expect(Storage::disk('local')->files('authorization-transitions'))->toBe($files);
    expect(fn () => $migration->down())->toThrow(LogicException::class);
});

test('consolidation refuses conflicting durations without replacing either definition', function (): void {
    Storage::fake('local');
    $grant = (array) DB::table('role_has_permissions')->first();
    DB::connection()->commit();
    (require database_path('migrations/central/2026_10_21_000000_create_role_permission_templates_table.php'))->up();
    DB::table('role_permission_templates')->insert([...$grant, 'duration_days' => $grant['duration_days'] === 1 ? 2 : 1]);
    $migration = require database_path('migrations/central/2026_10_21_051638_consolidate_central_role_permissions.php');

    try {
        expect(fn () => $migration->up())->toThrow(LogicException::class, 'Conflicting role permission durations');
        expect(Schema::hasTable('role_permission_templates'))->toBeTrue();
        $this->assertDatabaseHas('role_has_permissions', $grant);
        expect(Storage::disk('local')->files('authorization-transitions'))->toBe([]);
    } finally {
        Schema::dropIfExists('role_permission_templates');
    }
});
