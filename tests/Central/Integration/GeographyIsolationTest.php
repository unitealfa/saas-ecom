<?php

use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Roles\Role;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Geography\GeographicAreaService;
use App\Services\Central\Geography\GeographyCatalog;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([CountrySeeder::class, AuthorizationSeeder::class]);
});

function isolatedGeographyArea(): GeographicArea
{
    $area = new GeographicArea;
    $area->forceFill([
        'country_id' => Country::where('code', 'DZ')->firstOrFail()->id, 'parent_id' => null,
        'type' => 1, 'code' => '16', 'name_fr' => 'Alger', 'is_active' => true,
        'reference_source' => 'TEST SOURCE', 'reference_version' => 'test-1', 'effective_at' => null,
    ])->save();

    return $area->refresh();
}

test('geography remains central and cached lookups remain available inside a real tenant', function (): void {
    $area = isolatedGeographyArea();
    $catalog = app(GeographyCatalog::class);
    $expected = $catalog->find($area->uuid);
    $centralDatabase = $area->getConnection()->getDatabaseName();
    $tenant = null;
    try {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);
        expect(DB::connection()->getDatabaseName())->not->toBe($centralDatabase);
        expect(Schema::connection('tenant')->hasTable('geographic_areas'))->toBeFalse();
        expect((new GeographicArea)->getConnection()->getDatabaseName())->toBe($centralDatabase);
        expect(GeographicArea::where('uuid', $area->uuid)->firstOrFail()->name_fr)->toBe('Alger');
        expect($catalog->find($area->uuid))->toBe($expected);
        expect($catalog->listing([])['data'])->toHaveCount(1);
    } finally {
        tenancy()->end();
        if ($tenant !== null) {
            TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
        }
    }
    expect(tenancy()->initialized)->toBeFalse();
});

test('geography cache bypasses uncommitted changes and invalidates committed activation and country changes', function (): void {
    $area = isolatedGeographyArea();
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));
    $catalog = app(GeographyCatalog::class);
    expect($catalog->find($area->uuid)['name_fr'])->toBe('Alger');
    expect($catalog->listing([])['meta']['total'])->toBe(1);
    $connection = $area->getConnection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();
    try {
        $catalog->find($area->uuid);
        $catalog->listing([]);
        expect($connection->getQueryLog())->toBe([]);
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
    $connection->beginTransaction();
    try {
        $area->forceFill(['name_fr' => 'Nom non validé'])->save();
        expect($catalog->find($area->uuid)['name_fr'])->toBe('Nom non validé');
    } finally {
        $connection->rollBack();
    }
    expect($catalog->find($area->uuid)['name_fr'])->toBe('Alger');

    $area->refresh();
    app(GeographicAreaService::class)->setActive($admin, $area, false, $area->stateToken());
    expect(fn () => $catalog->find($area->uuid))->toThrow(ModelNotFoundException::class);
    expect($catalog->listing([])['meta']['total'])->toBe(0);
    $area->refresh();
    app(GeographicAreaService::class)->setActive($admin, $area, true, $area->stateToken());
    expect($catalog->find($area->uuid)['name_fr'])->toBe('Alger');
    $country = Country::where('code', 'DZ')->firstOrFail();
    $country->forceFill(['is_active' => false])->save();
    expect(fn () => $catalog->find($area->uuid))->toThrow(ModelNotFoundException::class);
    expect($catalog->listing([])['meta']['total'])->toBe(0);
});
