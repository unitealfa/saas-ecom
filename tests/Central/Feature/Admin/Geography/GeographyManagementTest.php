<?php

use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\User;
use App\Services\Central\Geography\GeographyImportService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\GeographyPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([CountrySeeder::class, AuthorizationSeeder::class]);
});

function geographyAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findByName('root', 'central'));

    return $user;
}

/** @param array<string, mixed> $changes */
function geographyArea(array $changes = []): GeographicArea
{
    $area = new GeographicArea;
    $area->forceFill(array_replace([
        'country_id' => Country::where('code', 'DZ')->firstOrFail()->id,
        'parent_id' => null, 'type' => 1, 'code' => '16', 'name_fr' => 'Alger',
        'name_ar' => null, 'is_active' => true, 'reference_source' => 'TEST SOURCE',
        'reference_version' => 'test-1', 'effective_at' => '2026-01-01',
    ], $changes))->save();

    return $area->refresh();
}

/** @param array<string, mixed> $changes
 * @return array<string, mixed>
 */
function geographyPayload(array $changes = []): array
{
    return array_replace([
        'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
        'parent_uuid' => null, 'type' => 1, 'code' => '31', 'name_fr' => 'Oran',
        'name_ar' => null, 'reference_source' => 'TEST SOURCE',
        'reference_version' => 'test-1', 'effective_at' => '2026-01-01',
    ], $changes);
}

test('geography endpoints reject staff without their required permissions', function (): void {
    $area = geographyArea();
    $user = User::factory()->create();
    $user->assignRole(Role::createWithPermissions('geography-unrelated', ['saas.users.view' => 7]));
    $this->actingAs($user, 'central');

    foreach (['index', 'create', 'show', 'edit'] as $action) {
        $this->get(route('central.geography.'.$action, in_array($action, ['show', 'edit'], true) ? $area : []))->assertForbidden();
    }
    $this->post(route('central.geography.store'), [])->assertForbidden();
    $this->put(route('central.geography.update', $area), [])->assertForbidden();
    $this->patch(route('central.geography.activate', $area), [])->assertForbidden();
    $this->delete(route('central.geography.archive', $area), [])->assertForbidden();
    expect(GeographicArea::count())->toBe(1);
});

test('geography administrators can render management screens using UUID binding', function (): void {
    $area = geographyArea();
    $this->actingAs(geographyAdmin(), 'central');

    foreach (['index', 'create', 'show', 'edit'] as $action) {
        $this->get(route('central.geography.'.$action, in_array($action, ['show', 'edit'], true) ? $area : []))->assertOk();
    }
    $this->get(route('central.geography.show', $area->id))->assertNotFound();
});

test('geography searches treat SQL wildcard characters as literal text', function (): void {
    $literal = geographyArea(['name_fr' => 'Zone_100%']);
    geographyArea(['code' => '31', 'name_fr' => 'Zone X 100']);

    foreach (['_', '%'] as $search) {
        expect(GeographicArea::search($search)->pluck('uuid')->all())->toBe([$literal->uuid]);
        $this->getJson(route('geography.lookup.index', ['search' => $search]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $literal->uuid);
    }
});

test('manual geography creation starts inactive and rejects duplicate identity', function (): void {
    $this->actingAs(geographyAdmin(), 'central');
    $this->post(route('central.geography.store'), geographyPayload())->assertSessionHasNoErrors()->assertRedirect();

    $area = GeographicArea::where('code', '31')->firstOrFail();
    expect($area->is_active)->toBeFalse();
    expect($area->reference_source)->toBe('TEST SOURCE');
    $this->post(route('central.geography.store'), geographyPayload())->assertSessionHasErrors();
    expect(GeographicArea::count())->toBe(1);
});

test('municipalities require a province from the same country', function (): void {
    $foreign = geographyArea(['country_id' => Country::where('code', 'FR')->firstOrFail()->id]);
    $this->actingAs(geographyAdmin(), 'central');

    $this->post(route('central.geography.store'), geographyPayload(['type' => 2]))->assertSessionHasErrors('parent_uuid');
    $this->post(route('central.geography.store'), geographyPayload(['type' => 2, 'parent_uuid' => $foreign->uuid]))->assertSessionHasErrors();
    expect(GeographicArea::count())->toBe(1);
});

test('editing geography preserves identity and rejects stale forms', function (): void {
    $area = geographyArea();
    $token = $area->stateToken();
    $this->actingAs(geographyAdmin(), 'central');
    $payload = ['name_fr' => 'Alger corrigé', 'name_ar' => null, 'reference_source' => 'TEST SOURCE', 'reference_version' => 'test-1', 'effective_at' => '2026-01-01', 'expected_state' => $token];

    $this->put(route('central.geography.update', $area), $payload)->assertSessionHasNoErrors()->assertRedirect();
    expect($area->refresh()->name_fr)->toBe('Alger corrigé');
    $this->put(route('central.geography.update', $area), array_replace($payload, ['name_fr' => 'Obsolète']))->assertSessionHasErrors();
    expect($area->refresh()->name_fr)->toBe('Alger corrigé');
    $this->put(route('central.geography.update', $area), array_replace($payload, ['expected_state' => $area->stateToken(), 'code' => '99']))->assertSessionHasErrors('code');
    expect($area->refresh()->code)->toBe('16');
});

test('disabling a province removes children from lookup without changing child flags', function (): void {
    $province = geographyArea();
    $municipality = geographyArea(['parent_id' => $province->id, 'type' => 2, 'code' => '1601', 'name_fr' => 'Alger Centre']);
    $this->getJson(route('geography.lookup.show', $municipality))->assertOk()->assertDontSee('country_id')->assertDontSee('parent_id');
    $this->actingAs(geographyAdmin(), 'central')->patch(route('central.geography.activate', $province), ['is_active' => false, 'expected_state' => $province->stateToken()])->assertSessionHasNoErrors();

    expect($municipality->refresh()->is_active)->toBeTrue();
    $this->getJson(route('geography.lookup.show', $municipality))->assertNotFound();
    $this->getJson(route('geography.lookup.show', $province))->assertNotFound();
});

test('archiving preserves dependent provinces and soft deletes an unreferenced municipality', function (): void {
    $province = geographyArea();
    $municipality = geographyArea(['parent_id' => $province->id, 'type' => 2, 'code' => '1601']);
    $this->actingAs(geographyAdmin(), 'central');

    $this->delete(route('central.geography.archive', $province), ['expected_state' => $province->stateToken()])->assertSessionHasErrors();
    $this->assertModelExists($province);
    $this->delete(route('central.geography.archive', $municipality), ['expected_state' => $municipality->stateToken()])->assertSessionHasNoErrors();
    $this->assertSoftDeleted($municipality);
    $this->delete(route('central.geography.archive', $province), ['expected_state' => $province->fresh()->stateToken()])->assertSessionHasErrors();
    $this->assertNotSoftDeleted($province);
});

test('geography seeding is idempotent and refuses to overwrite manual changes atomically', function (): void {
    $payload = [
        'metadata' => ['source' => 'TEST SOURCE', 'version' => 'test-1', 'effective_at' => '2026-01-01'],
        'wilayas' => [['id' => 99, 'code' => '99', 'name' => 'Wilaya test', 'ar_name' => null]],
        'communes' => [['id' => 99, 'wilaya_id' => 99, 'name' => 'Commune test', 'ar_name' => null, 'post_code' => '99001']],
    ];
    $service = app(GeographyImportService::class);
    expect($service->import($payload)['created'])->toBe(2);
    $identities = GeographicArea::orderBy('id')->get(['id', 'uuid'])->toArray();
    expect($service->import($payload)['ignored'])->toBe(2);
    expect(GeographicArea::orderBy('id')->get(['id', 'uuid'])->toArray())->toBe($identities);

    $province = GeographicArea::where('code', '99')->firstOrFail();
    $province->forceFill(['name_fr' => 'Correction manuelle'])->save();
    $payload['wilayas'][] = ['id' => 98, 'code' => '98', 'name' => 'Nouvelle wilaya', 'name_ar' => null];
    expect(fn () => $service->import($payload))->toThrow(ValidationException::class);
    expect($province->refresh()->name_fr)->toBe('Correction manuelle');
    expect(GeographicArea::count())->toBe(2);
});

test('carrier mappings prevent archival without deleting historical references', function (): void {
    $area = geographyArea();
    $carrier = DB::table('shipping_carriers')->insertGetId([
        'uuid' => (string) Str::uuid(), 'code' => 'test-carrier', 'name' => 'Test carrier',
        'adapter' => 'test', 'is_active' => false, 'reference_source' => 'TEST SOURCE',
        'reference_version' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $mapping = DB::table('carrier_geo_mappings')->insertGetId([
        'uuid' => (string) Str::uuid(), 'carrier_id' => $carrier, 'geographic_area_id' => $area->id,
        'zone_type' => 1, 'external_code' => '16', 'external_name' => 'Alger',
        'external_province_code' => '16', 'verification_source' => 'TEST SOURCE',
        'mapping_version' => 1, 'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs(geographyAdmin(), 'central')->delete(route('central.geography.archive', $area), [
        'expected_state' => $area->stateToken(),
    ])->assertSessionHasErrors();

    $this->assertNotSoftDeleted($area);
    $this->assertDatabaseHas('carrier_geo_mappings', ['id' => $mapping, 'geographic_area_id' => $area->id]);
});

test('public geography lookup returns 404 for inactive countries and numeric identities', function (): void {
    $area = geographyArea();
    $this->getJson(route('geography.lookup.show', $area->id))->assertNotFound();
    $country = Country::where('code', 'DZ')->firstOrFail();
    $country->forceFill(['is_active' => false])->save();

    $this->getJson(route('geography.lookup.show', $area))->assertNotFound();
    $this->assertModelExists($area);
});

test('the removed geography import endpoints return 404 even for the super administrator', function (string $method, string $path): void {
    $this->actingAs(geographyAdmin(), 'central');
    $this->{$method}($path)->assertNotFound();
    expect(GeographicArea::count())->toBe(0);
})->with([
    ['get', '/admin/geography/import'],
    ['post', '/admin/geography/import/preview'],
    ['post', '/admin/geography/import/confirm'],
]);

test('the geography catalogue no longer offers administrative imports', function (): void {
    $this->seed(GeographyPermissionSeeder::class);
    $this->actingAs(geographyAdmin(), 'central')->get(route('central.geography.index'))
        ->assertOk()->assertDontSee('Importer un référentiel');
    expect(Permission::where('name', 'saas.geography.import')->exists())->toBeFalse();
});

test('the removal migration deletes only the obsolete unassigned permission', function (): void {
    $this->seed(GeographyPermissionSeeder::class);
    Permission::create(['name' => 'saas.geography.import', 'label' => 'Ancien droit']);
    $area = geographyArea();
    $before = Permission::where('name', '<>', 'saas.geography.import')->orderBy('id')->pluck('uuid', 'id')->all();
    $migration = require database_path('migrations/central/2026_10_31_174124_remove_obsolete_geography_import_permission.php');
    $migration->up();
    $this->seed(GeographyPermissionSeeder::class);

    expect(Permission::where('name', 'saas.geography.import')->exists())->toBeFalse()
        ->and(Permission::orderBy('id')->pluck('uuid', 'id')->all())->toBe($before);
    $this->assertModelExists($area);
});

test('the removal migration preserves existing assignments until explicitly resolved', function (bool $direct): void {
    $permission = Permission::create(['name' => 'saas.geography.import', 'label' => 'Ancien droit']);
    if ($direct) {
        $user = User::factory()->create();
        $user->assignRole(Role::createWithPermissions('staff-without-import', ['saas.users.view' => 7]));
        $user->givePermissionTo($permission);
    } else {
        Role::createWithPermissions('staff-with-import', ['saas.geography.import' => 7]);
    }
    $migration = require database_path('migrations/central/2026_10_31_174124_remove_obsolete_geography_import_permission.php');

    expect(fn () => $migration->up())->toThrow(LogicException::class);
    $this->assertModelExists($permission);
})->with([true, false]);
