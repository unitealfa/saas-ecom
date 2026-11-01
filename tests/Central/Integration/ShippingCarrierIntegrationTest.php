<?php

use App\Models\Central\Roles\Role;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Shipping\ShippingCarrierCatalog;
use App\Services\Central\Shipping\ShippingCarrierService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([CountrySeeder::class, AuthorizationSeeder::class]);
});

test('carrier cache avoids repeated queries and follows committed changes without leaking rollbacks', function (): void {
    $carrier = ShippingCarrier::factory()->create(['is_active' => true]);
    $catalog = app(ShippingCarrierCatalog::class);
    $expected = $catalog->active();
    expect($expected)->toBe([['uuid' => $carrier->uuid, 'code' => $carrier->code, 'name' => 'Yalidine']]);
    $connection = $carrier->getConnection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();
    try {
        expect($catalog->active())->toBe($expected);
        expect($connection->getQueryLog())->toBe([]);
    } finally {
        $connection->disableQueryLog();
    }
    $carrier->update(['name' => 'Nouveau nom']);
    expect($catalog->active()[0]['name'])->toBe('Nouveau nom');
    $connection->beginTransaction();
    try {
        $carrier->forceFill(['is_active' => false])->save();
        expect($catalog->active())->toBe([]);
    } finally {
        $connection->rollBack();
    }
    expect($catalog->active()[0]['name'])->toBe('Nouveau nom');
    $carrier->refresh()->forceFill(['is_active' => false])->save();
    expect($catalog->active())->toBe([]);
});

test('tenant carrier accounts block archival and central public cache remains isolated', function (): void {
    $carrier = ShippingCarrier::factory()->create(['is_active' => true]);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));
    $catalog = app(ShippingCarrierCatalog::class);
    $expected = $catalog->active();
    $tenant = null;
    try {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        $central = $carrier->getConnection();
        $central->enableQueryLog();
        $central->flushQueryLog();
        try {
            expect($catalog->active())->toBe($expected)->and($central->getQueryLog())->toBe([]);
        } finally {
            $central->disableQueryLog();
        }
        expect(fn () => app(ShippingCarrierService::class)->setActive($admin, $carrier, false, $carrier->managementState()))
            ->toThrow(HttpException::class);
        DB::connection('tenant')->table('carrier_accounts')->insert([
            'uuid' => (string) Str::uuid(), 'created_by_id' => DB::connection('tenant')->table('users')->value('id'),
            'carrier_uuid' => $carrier->uuid, 'label' => 'Compte boutique de test',
            'adapter' => $carrier->adapter, 'is_active' => false,
        ]);
        tenancy()->end();
        expect(fn () => app(ShippingCarrierService::class)->archive($admin, $carrier, $carrier->managementState()))
            ->toThrow(ValidationException::class, 'Ce transporteur est utilisé.');
        expect(tenancy()->initialized)->toBeFalse()->and($carrier->refresh()->trashed())->toBeFalse();
    } finally {
        tenancy()->end();
        if ($tenant !== null) {
            TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
        }
    }
});

test('unreadable tenant references refuse archival and always restore the central context', function (): void {
    $carrier = ShippingCarrier::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));
    $tenant = null;
    try {
        $tenant = Tenant::factory()->create();
        $tenant->run(fn () => Schema::connection('tenant')->rename('carrier_accounts', 'unavailable_carrier_accounts'));
        expect(fn () => app(ShippingCarrierService::class)->archive($admin, $carrier, $carrier->managementState()))
            ->toThrow(ValidationException::class, 'Les références des boutiques sont indisponibles.');
        expect(tenancy()->initialized)->toBeFalse()->and(DB::getDefaultConnection())->toBe('mysql')
            ->and($carrier->refresh()->trashed())->toBeFalse()->and($carrier->reference_version)->toBe(1);
    } finally {
        tenancy()->end();
        if ($tenant !== null) {
            TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
        }
    }
});
