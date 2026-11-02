<?php

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Roles\Role;
use App\Models\Central\Shipping\PickupPoint;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Shipping\PickupPointCatalog;
use App\Services\Central\Shipping\PickupPointService;
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

/** @return array{User, PickupPoint, array<string, mixed>} */
function pickupCacheFixture(): array
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));
    $carrier = ShippingCarrier::factory()->create();
    $area = new GeographicArea;
    $area->forceFill(['country_id' => Country::query()->where('code', 'DZ')->sole()->id,
        'type' => GeoZoneTypeEnum::PROVINCE, 'code' => '16', 'name_fr' => 'Alger',
        'is_active' => true, 'reference_source' => 'test', 'reference_version' => 'test-1'])->save();
    $data = ['carrier_uuid' => $carrier->uuid, 'province_uuid' => $area->uuid, 'municipality_uuid' => null,
        'external_code' => '016', 'name' => 'Alger externe', 'address' => 'Adresse de test', 'phone' => null, 'map_url' => null,
        'reference_source' => 'Export de test'];

    return [$actor, app(PickupPointService::class)->save($actor, $data), $data];
}

test('pickup public presentation cache is stable and follows commits without leaking rollbacks', function (): void {
    [$actor, $mapping, $data] = pickupCacheFixture();
    $catalog = app(PickupPointCatalog::class);
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
        $mapping->forceFill(['name' => 'Modification non validée'])->save();
        expect($catalog->find($mapping->uuid)['name'])->toBe('Modification non validée');
    } finally {
        $connection->rollBack();
    }
    expect($catalog->find($mapping->uuid)['name'])->toBe('Alger externe');
    $mapping->refresh();
    app(PickupPointService::class)->save($actor, [...$data, 'name' => 'Modification validée'],
        $mapping, $mapping->managementState());
    expect($catalog->find($mapping->uuid)['name'])->toBe('Modification validée');
});

test('pickup reads remain central inside tenancy while central mutations are forbidden', function (): void {
    [$actor, $mapping, $data] = pickupCacheFixture();
    $catalog = app(PickupPointCatalog::class);
    $expected = $catalog->find($mapping->uuid);
    $centralDatabase = $mapping->getConnection()->getDatabaseName();
    $tenant = null;
    try {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        expect(DB::connection()->getDatabaseName())->not->toBe($centralDatabase);
        expect((new PickupPoint)->getConnection()->getDatabaseName())->toBe($centralDatabase);
        expect($catalog->find($mapping->uuid))->toBe($expected);
        expect(fn () => app(PickupPointService::class)->save($actor, $data))
            ->toThrow(HttpException::class);
    } finally {
        tenancy()->end();
        if ($tenant !== null) {
            TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
        }
    }
    expect(tenancy()->initialized)->toBeFalse()->and(PickupPoint::count())->toBe(1);
});
