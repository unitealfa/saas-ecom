<?php

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\User;
use App\Services\Central\Shipping\CarrierGeoImportService;
use App\Services\Central\Shipping\CarrierGeoMappingService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CarrierMappingPermissionSeeder;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed([CountrySeeder::class, AuthorizationSeeder::class]);
});

function mappingAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findByName('root', 'central'));

    return $user;
}

function mappingStaff(string $permission): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('mapping-'.Str::random(12), [$permission => 7]));

    return $actor;
}

function mappingArea(string $code = '16', ?GeographicArea $parent = null): GeographicArea
{
    $area = new GeographicArea;
    $area->forceFill(['country_id' => Country::query()->where('code', 'DZ')->sole()->id,
        'parent_id' => $parent?->id,
        'type' => $parent === null ? GeoZoneTypeEnum::PROVINCE : GeoZoneTypeEnum::MUNICIPALITY,
        'code' => $code, 'name_fr' => 'Zone de test '.$code, 'is_active' => true,
        'reference_source' => 'test', 'reference_version' => 'test-1'])->save();

    return $area;
}

/** @return array<string, string> */
function mappingData(ShippingCarrier $carrier, GeographicArea $area, string $code = '016'): array
{
    return ['carrier_uuid' => $carrier->uuid, 'geographic_area_uuid' => $area->uuid,
        'external_code' => $code, 'external_name' => 'Zone externe', 'external_province_code' => $code,
        'verification_source' => 'Export transporteur contrôlé pour ce test'];
}

/** @param list<array<string, string>> $rows */
function mappingCsv(array $rows): UploadedFile
{
    $stream = fopen('php://temp', 'w+');
    fputcsv($stream, CarrierGeoImportService::HEADERS, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($stream, array_map(fn (string $key): string => $row[$key], CarrierGeoImportService::HEADERS), ',', '"', '');
    }
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);

    return UploadedFile::fake()->createWithContent('mappings.csv', $csv);
}

/** @return array<string, mixed> */
function mappingPreview(ShippingCarrier $carrier, UploadedFile $file): array
{
    test()->post(route('central.carrier-mappings.preview'), ['carrier_uuid' => $carrier->uuid, 'file' => $file])
        ->assertRedirect(route('central.carrier-mappings.import'));

    return test()->get(route('central.carrier-mappings.import'))->assertOk()
        ->assertSee('Confirmer l’import vérifié')->viewData('preview');
}

test('mapping permissions remain independent and do not require carrier administration rights', function (): void {
    $carrier = ShippingCarrier::factory()->create();
    $mapping = app(CarrierGeoMappingService::class)->save(mappingAdmin(), mappingData($carrier, mappingArea()));
    foreach (['view' => 'viewAny', 'create' => 'create', 'update' => 'update', 'import' => 'import', 'archive' => 'archive'] as $permission => $ability) {
        $actor = mappingStaff('saas.carrier-mappings.'.$permission);
        foreach (['viewAny' => CarrierGeoMapping::class, 'create' => CarrierGeoMapping::class,
            'import' => CarrierGeoMapping::class, 'update' => $mapping, 'archive' => $mapping] as $candidate => $target) {
            expect(Gate::forUser($actor)->allows($candidate, $target))->toBe($candidate === $ability);
        }
    }
});

test('every mapping endpoint rejects staff without the matching permission', function (): void {
    $carrier = ShippingCarrier::factory()->create();
    $mapping = app(CarrierGeoMappingService::class)->save(mappingAdmin(), mappingData($carrier, mappingArea()));
    $this->actingAs(mappingStaff('saas.users.view'), 'central');
    foreach (['index', 'create', 'import'] as $action) {
        $this->get(route('central.carrier-mappings.'.$action))->assertForbidden();
    }
    $this->get(route('central.carrier-mappings.edit', $mapping))->assertForbidden();
    $this->post(route('central.carrier-mappings.store'), [])->assertForbidden();
    $this->put(route('central.carrier-mappings.update', $mapping), [])->assertForbidden();
    $this->delete(route('central.carrier-mappings.archive', $mapping), [])->assertForbidden();
    $this->post(route('central.carrier-mappings.preview'), [])->assertForbidden();
    $this->post(route('central.carrier-mappings.confirm'), [])->assertForbidden();
});

test('mapping creation uses actual foreign keys and a reader sees the connected interface', function (): void {
    $carrier = ShippingCarrier::factory()->create();
    $area = mappingArea();
    $creator = mappingStaff('saas.carrier-mappings.create');
    $this->actingAs($creator, 'central')->get(route('central.carrier-mappings.create'))->assertOk()
        ->assertSee('geographic_area_uuid')->assertSee('endpoint: '.Js::from(route('geography.lookup.index')), false);
    $this->post(route('central.carrier-mappings.store'), mappingData($carrier, $area))
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $mapping = CarrierGeoMapping::sole();
    expect($mapping->carrier_id)->toBe($carrier->id)->and($mapping->geographic_area_id)->toBe($area->id)
        ->and($mapping->zone_type)->toBe(GeoZoneTypeEnum::PROVINCE)
        ->and($mapping->external_code)->toBe('016')->and($mapping->mapping_version)->toBe(1)
        ->and($mapping->is_active)->toBeTrue()->and($mapping->verified_at)->not->toBeNull();
    $this->actingAs(mappingStaff('saas.carrier-mappings.view'), 'central')
        ->get(route('central.carrier-mappings.index'))->assertOk()->assertSee('Yalidine')
        ->assertSee($area->name_fr)->assertSee('016')->assertDontSee('Créer une correspondance')
        ->assertDontSee('>Modifier<', false)->assertDontSee('>Archiver<', false);
    $this->get(route('central.carrier-mappings.edit', $mapping->id))->assertNotFound();
});

test('mapping uniqueness is per carrier and zone while external codes may repeat', function (): void {
    $carrier = ShippingCarrier::factory()->create();
    $area = mappingArea();
    $this->actingAs(mappingAdmin(), 'central');
    $data = mappingData($carrier, $area);
    $this->post(route('central.carrier-mappings.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('central.carrier-mappings.store'), $data)->assertSessionHasErrors('geographic_area_uuid');
    $this->post(route('central.carrier-mappings.store'), mappingData($carrier, mappingArea('31')))->assertSessionHasNoErrors();
    $other = ShippingCarrier::factory()->create(['code' => 'other', 'adapter' => ShippingCarrier::UNCONFIGURED_ADAPTER]);
    $this->post(route('central.carrier-mappings.store'), mappingData($other, $area))->assertSessionHasNoErrors();
    expect(CarrierGeoMapping::count())->toBe(3);
});

test('mapping updates preserve identity and stale forms or moving relationships are refused', function (): void {
    $actor = mappingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $area = mappingArea();
    $data = mappingData($carrier, $area);
    $mapping = app(CarrierGeoMappingService::class)->save($actor, $data);
    $uuid = $mapping->uuid;
    $expected = $mapping->managementState();
    $this->actingAs($actor, 'central')->get(route('central.carrier-mappings.edit', $mapping))->assertOk();
    $this->put(route('central.carrier-mappings.update', $mapping),
        [...$data, 'external_name' => 'Nouveau nom', 'expected_state' => $expected])->assertSessionHasNoErrors();
    $this->put(route('central.carrier-mappings.update', $mapping),
        [...$data, 'expected_state' => $expected])->assertSessionHasErrors('conflict');
    $mapping->refresh();
    $this->put(route('central.carrier-mappings.update', $mapping),
        [...$data, 'geographic_area_uuid' => mappingArea('31')->uuid, 'expected_state' => $mapping->managementState()])
        ->assertSessionHasErrors('mapping');
    expect($mapping->refresh()->uuid)->toBe($uuid)->and($mapping->mapping_version)->toBe(2)
        ->and($mapping->external_name)->toBe('Nouveau nom');
});

test('archiving deactivates a mapping without deleting it or letting import restore it', function (): void {
    $actor = mappingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $data = mappingData($carrier, mappingArea());
    $mapping = app(CarrierGeoMappingService::class)->save($actor, $data);
    $this->actingAs($actor, 'central')->delete(route('central.carrier-mappings.archive', $mapping),
        ['expected_state' => $mapping->managementState()])->assertSessionHasNoErrors();
    expect($mapping->refresh()->is_active)->toBeFalse()->and($mapping->mapping_version)->toBe(2);
    $this->get(route('central.carrier-mappings.edit', $mapping))->assertForbidden();
    $this->post(route('central.carrier-mappings.store'), $data)->assertSessionHasErrors('geographic_area_uuid');
    $this->post(route('central.carrier-mappings.preview'),
        ['carrier_uuid' => $carrier->uuid, 'file' => mappingCsv([$data])])->assertSessionHasErrors('file');
    expect(CarrierGeoMapping::count())->toBe(1)
        ->and(Activity::forSubject($mapping)->where('event', 'carrier_mapping_archived')->count())->toBe(1);
});

test('mapping creation refuses inactive zones archived carriers and forged verification fields', function (): void {
    $actor = mappingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $area = mappingArea();
    $data = mappingData($carrier, $area);
    $this->actingAs($actor, 'central')->post(route('central.carrier-mappings.store'),
        [...$data, 'is_active' => true, 'verified_at' => now()->toDateTimeString(), 'mapping_version' => 42])
        ->assertSessionHasErrors(['is_active', 'verified_at', 'mapping_version']);
    $area->forceFill(['is_active' => false])->save();
    $this->post(route('central.carrier-mappings.store'), $data)->assertSessionHasErrors('geographic_area_uuid');
    $carrier->delete();
    $this->post(route('central.carrier-mappings.store'), $data)->assertSessionHasErrors('carrier_uuid');
    expect(CarrierGeoMapping::count())->toBe(0);
});

test('commune mappings derive the type and keep the explicit external province code', function (): void {
    $carrier = ShippingCarrier::factory()->create();
    $area = mappingArea('1601', mappingArea());
    $this->actingAs(mappingAdmin(), 'central')->post(route('central.carrier-mappings.store'),
        [...mappingData($carrier, $area, 'city-001'), 'external_province_code' => 'province-16'])
        ->assertSessionHasNoErrors();
    expect(CarrierGeoMapping::sole()->zone_type)->toBe(GeoZoneTypeEnum::MUNICIPALITY)
        ->and(CarrierGeoMapping::sole()->external_province_code)->toBe('province-16');
});

test('external zone and province codes may use distinct formats defined by the carrier', function (): void {
    $carrier = ShippingCarrier::factory()->create();
    $area = mappingArea();
    $this->actingAs(mappingAdmin(), 'central')->post(route('central.carrier-mappings.store'), [
        ...mappingData($carrier, $area, 'wilaya-016'), 'external_province_code' => '016',
    ])->assertSessionHasNoErrors();
    expect(CarrierGeoMapping::sole()->external_code)->toBe('wilaya-016')
        ->and(CarrierGeoMapping::sole()->external_province_code)->toBe('016');
});

test('CSV preview writes nothing then confirmation creates and updates atomically without changing UUIDs', function (): void {
    $actor = mappingStaff('saas.carrier-mappings.import');
    $carrier = ShippingCarrier::factory()->create();
    $oldData = mappingData($carrier, mappingArea());
    $old = app(CarrierGeoMappingService::class)->save(mappingAdmin(), $oldData);
    $oldUuid = $old->uuid;
    $new = mappingData($carrier, mappingArea('31'), '031');
    $changed = [...$oldData, 'external_name' => 'Nom contrôlé'];
    $this->actingAs($actor, 'central')->get(route('central.carrier-mappings.import'))->assertOk();
    $preview = mappingPreview($carrier, mappingCsv([$changed, $new]));
    expect(CarrierGeoMapping::count())->toBe(1)->and($old->refresh()->mapping_version)->toBe(1);
    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();
    try {
        $this->post(route('central.carrier-mappings.confirm'), ['token' => $preview['token']])->assertSessionHasNoErrors();
        $geographyReads = array_filter(DB::connection()->getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], 'from `geographic_areas`'));
        expect($geographyReads)->toHaveCount(2);
    } finally {
        DB::connection()->disableQueryLog();
        DB::connection()->flushQueryLog();
    }
    expect(CarrierGeoMapping::count())->toBe(2)->and($old->refresh()->uuid)->toBe($oldUuid)
        ->and($old->mapping_version)->toBe(2)->and($old->external_name)->toBe('Nom contrôlé')
        ->and($old->synced_at)->not->toBeNull();
    $this->post(route('central.carrier-mappings.confirm'), ['token' => $preview['token']])->assertSessionHasErrors('file');
    expect(CarrierGeoMapping::count())->toBe(2);
});

test('invalid CSV headers duplicate zones missing data and invalid UTF8 are rejected in preview', function (): void {
    $carrier = ShippingCarrier::factory()->create();
    $area = mappingArea();
    $data = mappingData($carrier, $area);
    $header = implode(',', CarrierGeoImportService::HEADERS)."\n";
    $this->actingAs(mappingAdmin(), 'central');
    foreach ([
        UploadedFile::fake()->createWithContent('bad.csv', "code,zone\n16,16"),
        UploadedFile::fake()->createWithContent('empty.csv', $header),
        mappingCsv([$data, $data]),
        mappingCsv([[...$data, 'geographic_area_uuid' => (string) Str::uuid()]]),
        mappingCsv([[...$data, 'external_name' => '']]),
        mappingCsv([[...$data, 'external_name' => chr(255)]]),
    ] as $file) {
        $this->post(route('central.carrier-mappings.preview'), ['carrier_uuid' => $carrier->uuid, 'file' => $file])
            ->assertSessionHasErrors('file');
    }
    expect(CarrierGeoMapping::count())->toBe(0);
});

test('CSV previews are account bound and expire without creating mappings', function (): void {
    $carrier = ShippingCarrier::factory()->create();
    $actor = mappingAdmin();
    $data = mappingData($carrier, mappingArea());
    $this->actingAs($actor, 'central');
    $preview = mappingPreview($carrier, mappingCsv([$data]));
    $this->actingAs(mappingAdmin(), 'central')->post(route('central.carrier-mappings.confirm'),
        ['token' => $preview['token']])->assertSessionHasErrors('file');
    $this->travel(16)->minutes();
    $this->actingAs($actor, 'central')->post(route('central.carrier-mappings.confirm'),
        ['token' => $preview['token']])->assertSessionHasErrors('file');
    expect(CarrierGeoMapping::count())->toBe(0);
});

test('CSV accepts semicolon UTF8 BOM and preserves an unchanged mapping version', function (): void {
    $actor = mappingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $data = mappingData($carrier, mappingArea());
    $mapping = app(CarrierGeoMappingService::class)->save($actor, $data);
    $content = "\xEF\xBB\xBF".implode(';', CarrierGeoImportService::HEADERS)."\n"
        .implode(';', array_map(fn (string $key): string => $key === 'geographic_area_uuid' ? strtoupper($data[$key]) : $data[$key], CarrierGeoImportService::HEADERS));
    $this->actingAs($actor, 'central');
    $preview = mappingPreview($carrier, UploadedFile::fake()->createWithContent('mappings.csv', $content));
    $this->post(route('central.carrier-mappings.confirm'), ['token' => $preview['token']])
        ->assertSessionHasNoErrors()->assertSessionHas('status', 'Import confirmé : 0 créée(s), 0 modifiée(s), 1 inchangée(s).');
    expect($mapping->refresh()->external_code)->toBe('016')->and($mapping->mapping_version)->toBe(1);
});

test('a stale imported row rolls back preceding rows and audits instead of overwriting a newer edit', function (): void {
    $actor = mappingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $oldData = mappingData($carrier, mappingArea());
    $old = app(CarrierGeoMappingService::class)->save($actor, $oldData);
    $new = mappingData($carrier, mappingArea('31'), '031');
    $this->actingAs($actor, 'central');
    $preview = mappingPreview($carrier, mappingCsv([$new, $oldData]));
    app(CarrierGeoMappingService::class)->save($actor, [...$oldData, 'external_name' => 'Modification récente'], $old, $old->managementState());
    $auditCount = Activity::count();
    $this->followingRedirects()->post(route('central.carrier-mappings.confirm'), ['token' => $preview['token']])
        ->assertOk()->assertSee('Cette correspondance a changé.');
    expect(CarrierGeoMapping::count())->toBe(1)->and($old->refresh()->external_name)->toBe('Modification récente')
        ->and(Activity::count())->toBe($auditCount);
});

test('revoking import permission between preview and confirmation prevents all writes', function (): void {
    $actor = mappingStaff('saas.carrier-mappings.import');
    $carrier = ShippingCarrier::factory()->create();
    $data = mappingData($carrier, mappingArea());
    $this->actingAs($actor, 'central');
    $preview = mappingPreview($carrier, mappingCsv([$data]));
    $actor->revokePermissionTo('saas.carrier-mappings.import');
    $this->post(route('central.carrier-mappings.confirm'), ['token' => $preview['token']])->assertForbidden();
    expect(CarrierGeoMapping::count())->toBe(0);
});

test('mapping permission seeding is idempotent and preserves existing assignments', function (): void {
    $actor = mappingStaff('saas.carrier-mappings.view');
    $uuids = Permission::query()->pluck('uuid', 'id')->all();
    $assignments = DB::table('model_has_permissions')->get()->toArray();
    $this->seed(CarrierMappingPermissionSeeder::class);
    $this->seed(CarrierMappingPermissionSeeder::class);
    expect(Permission::query()->pluck('uuid', 'id')->all())->toBe($uuids)
        ->and(DB::table('model_has_permissions')->get()->toArray())->toEqual($assignments)
        ->and(Permission::where('name', 'like', 'saas.carrier-mappings.%')->count())->toBe(5)
        ->and(Gate::forUser($actor)->allows('viewAny', CarrierGeoMapping::class))->toBeTrue();
});
