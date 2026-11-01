<?php

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Roles\Role;
use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Shipping\CarrierGeoMappingCatalog;
use App\Services\Central\Shipping\CarrierGeoMappingService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([CountrySeeder::class, AuthorizationSeeder::class]);
});

/** @return array{User, CarrierGeoMapping, array<string, string>} */
function mappingCacheFixture(): array
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));
    $carrier = ShippingCarrier::factory()->create();
    $area = new GeographicArea;
    $area->forceFill(['country_id' => Country::query()->where('code', 'DZ')->sole()->id,
        'type' => GeoZoneTypeEnum::PROVINCE, 'code' => '16', 'name_fr' => 'Alger',
        'is_active' => true, 'reference_source' => 'test', 'reference_version' => 'test-1'])->save();
    $data = ['carrier_uuid' => $carrier->uuid, 'geographic_area_uuid' => $area->uuid,
        'external_code' => '016', 'external_name' => 'Alger externe', 'external_province_code' => '016',
        'verification_source' => 'Export de test'];

    return [$actor, app(CarrierGeoMappingService::class)->save($actor, $data), $data];
}

test('mapping public presentation cache is stable and follows commits without leaking rollbacks', function (): void {
    [$actor, $mapping, $data] = mappingCacheFixture();
    $catalog = app(CarrierGeoMappingCatalog::class);
    $expected = $catalog->find($mapping->uuid);
    $connection = $mapping->getConnection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();
    $storage = app()->storagePath();
    try {
        expect($catalog->find($mapping->uuid))->toBe($expected)->and($connection->getQueryLog())->toBe([]);
        app()->useStoragePath($storage.'/temporary-tenant-storage');
        expect($catalog->find($mapping->uuid))->toBe($expected)->and($connection->getQueryLog())->toBe([]);
    } finally {
        app()->useStoragePath($storage);
        $connection->disableQueryLog();
    }
    $connection->beginTransaction();
    try {
        $mapping->forceFill(['external_name' => 'Modification non validée'])->save();
        expect($catalog->find($mapping->uuid)['external_name'])->toBe('Modification non validée');
    } finally {
        $connection->rollBack();
    }
    expect($catalog->find($mapping->uuid)['external_name'])->toBe('Alger externe');
    $mapping->refresh();
    app(CarrierGeoMappingService::class)->save($actor, [...$data, 'external_name' => 'Modification validée'],
        $mapping, $mapping->managementState());
    expect($catalog->find($mapping->uuid)['external_name'])->toBe('Modification validée');
});

test('mapping reads remain central inside tenancy while central mutations are forbidden', function (): void {
    [$actor, $mapping, $data] = mappingCacheFixture();
    $catalog = app(CarrierGeoMappingCatalog::class);
    $expected = $catalog->find($mapping->uuid);
    $centralDatabase = $mapping->getConnection()->getDatabaseName();
    $tenant = null;
    try {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        expect(DB::connection()->getDatabaseName())->not->toBe($centralDatabase);
        expect((new CarrierGeoMapping)->getConnection()->getDatabaseName())->toBe($centralDatabase);
        expect($catalog->find($mapping->uuid))->toBe($expected);
        expect(fn () => app(CarrierGeoMappingService::class)->save($actor, $data))
            ->toThrow(HttpException::class);
    } finally {
        tenancy()->end();
        if ($tenant !== null) {
            TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
        }
    }
    expect(tenancy()->initialized)->toBeFalse()->and(CarrierGeoMapping::count())->toBe(1);
});
