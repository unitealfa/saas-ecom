<?php

use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Tenants\Tenant;
use Database\Seeders\Central\LocalDevelopmentSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('normal and demonstration seeds share the same complete geographic reference', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(DatabaseSeeder::class);
    $identities = GeographicArea::orderBy('id')->pluck('uuid', 'id')->all();

    try {
        $this->seed(LocalDevelopmentSeeder::class);
        expect(GeographicArea::count())->toBe(1610);
        expect(GeographicArea::where('reference_source', 'local-fixture')->exists())->toBeFalse();
        expect(GeographicArea::orderBy('id')->pluck('uuid', 'id')->all())->toBe($identities);
        $province = GeographicArea::where('type', 1)->where('code', '16')->firstOrFail();
        $commune = GeographicArea::where('type', 2)->where('code', 'dz-16001')->firstOrFail();
        expect($commune->parent_id)->toBe($province->id);

        foreach (Tenant::all() as $tenant) {
            $tenant->run(function () use ($province, $commune): void {
                expect(DB::connection('tenant')->getSchemaBuilder()->hasTable('geographic_areas'))->toBeFalse();
                $addresses = DB::connection('tenant')->table('shop_addresses')->where('record_type', 1)->get();
                expect($addresses)->not->toBeEmpty();
                foreach ($addresses as $address) {
                    expect($address->province_uuid)->toBe($province->uuid);
                    expect($address->municipality_uuid)->toBe($commune->uuid);
                }
            });
        }
        $this->seed(LocalDevelopmentSeeder::class);
        expect(GeographicArea::count())->toBe(1610);
        expect(GeographicArea::orderBy('id')->pluck('uuid', 'id')->all())->toBe($identities);
    } finally {
        tenancy()->end();
        foreach (Tenant::all() as $tenant) {
            TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
        }
    }
});
