<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\DatabaseDiagnostics;
use Database\Seeders\Central\LocalDevelopmentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

beforeEach(function (): void {
    isolateLocalFixtureStorage();
    config(['tenancy.database.suffix' => '', 'tenancy.saas_base_domain' => 'aydra.localhost']);
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
});

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        $name = $tenant->database()->getName();

        TestCase::dropIsolatedMysqlDatabase($name);
    }
    cleanLocalFixtureStorage();
});

test('local fixtures populate coherent owners shops stock returns and billing without duplicates', function (): void {
    Storage::fake('local');
    $this->seed(LocalDevelopmentSeeder::class);
    expect(User::count())->toBe(3)->and(Tenant::count())->toBe(2);
    $this->assertDatabaseCount('domains', 2);
    $this->assertDatabaseCount('saas_invoices', 4);
    $this->assertDatabaseCount('saas_transfers', 4);
    $snapshot = [];

    foreach (Tenant::with('owner')->get() as $tenant) {
        $snapshot[] = [$tenant->id, $tenant->uuid, $tenant->database()->getName()];
        expect($tenant->domains()->firstOrFail()->tenant_id)->toBe($tenant->id);
        $tenant->run(function () use ($tenant): void {
            $db = DB::connection('tenant');
            expect($db->table('users')->count())->toBe(2);
            expect($db->table('users')->where('central_user_uuid', $tenant->owner->uuid)->count())->toBe(1);
            expect($db->table('shop')->value('tenant_uuid'))->toBe($tenant->uuid);
            $this->assertDatabaseCount('shop', 1, 'tenant');
            $profile = $db->table('shop')->sole();
            expect($profile->business_type)->toBe('OTHER');
            expect($profile->theme_code)->toBe('default');
            expect(json_decode($profile->colors, true, flags: JSON_THROW_ON_ERROR))->toEqual(['primary' => '#2563EB', 'secondary' => '#FFFFFF']);
            expect($profile->cart_lifetime_days)->toBe(7);
            expect($db->table('products')->count())->toBe(20);
            expect($db->table('orders')->count())->toBe(50);
            expect($db->table('invoices')->count())->toBe(17);
            expect($db->table('order_returns')->count())->toBe(2);
            expect($db->table('customer_adjustments')->sum('amount'))->toEqual(100);

            foreach ($db->table('product_variants')->get() as $variant) {
                $movements = $db->table('stock_movements')->where('variant_id', $variant->id);
                expect($movements->sum('physical_delta'))->toEqual($variant->physical_stock);
                expect($movements->sum('reserved_delta'))->toEqual($variant->reserved_stock);
                expect($movements->sum('quarantine_delta'))->toEqual($variant->quarantine_stock);
                expect($db->table('order_items')->where('variant_id', $variant->id)->where('reservation_status', 1)->sum('quantity'))->toEqual($variant->reserved_stock);
            }
            expect($db->table('carrier_fees')->where('fee_type', 2)->orderBy('amount')->pluck('amount')->map(fn ($amount): int => (int) $amount)->all())->toBe([0, 300]);
            expect($db->table('order_revisions')->where('return_cost_recovery_amount', '>', 0)->count())->toBe(1);
            foreach ($db->table('orders')->where('order_type', 4)->get() as $resend) {
                $old = $db->table('order_revisions')->where('order_id', $resend->original_order_id)->first();
                $new = $db->table('order_revisions')->where('order_id', $resend->id)->first();
                expect([$new->recipient_last_name, $new->phone, $new->email])->toBe([$old->recipient_last_name, $old->phone, $old->email]);
            }
            $pdf = $db->table('media')->first();
            expect($pdf)->not->toBeNull();
            expect(Storage::disk('local')->get($pdf->storage_key))->toStartWith('%PDF-1.4')->toContain('LOCAL TEST FIXTURE');
        });
    }

    $this->seed(LocalDevelopmentSeeder::class);
    expect(User::count())->toBe(3)->and(Tenant::count())->toBe(2);
    expect(Tenant::get()->map(fn (Tenant $tenant): array => [$tenant->id, $tenant->uuid, $tenant->database()->getName()])->all())->toBe($snapshot);
    foreach (Tenant::get() as $tenant) {
        $tenant->run(fn () => expect(DB::connection('tenant')->table('orders')->count())->toBe(50));
    }
});

test('local seeding is refused in production before inserting accounts', function (): void {
    $this->app['env'] = 'production';
    expect(fn () => $this->seed(LocalDevelopmentSeeder::class))->toThrow(LogicException::class);
    expect(User::count())->toBe(0);
});

test('local seeding refuses to mix fixtures into existing central accounts', function (): void {
    $user = User::factory()->create();
    expect(fn () => $this->seed(LocalDevelopmentSeeder::class))->toThrow(LogicException::class);
    expect(User::count())->toBe(1)->and($user->fresh()->uuid)->toBe($user->uuid);
});

test('the full local fixture seeder and query diagnostics work with real MySQL constraints', function (): void {
    $original = config('tenancy.database');
    $default = DB::getDefaultConnection();
    $connection = config('database.connections.mysql');
    $connection['database'] = 'information_schema';
    config(['database.connections.fixture_admin' => $connection]);
    $prefix = 'aydra_seed_test_'.substr(str_replace('-', '', (string) Str::uuid()), 0, 12).'_';
    $centralName = $prefix.'central';

    try {
        DB::connection('fixture_admin')->statement('CREATE DATABASE `'.$centralName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        $connection['database'] = $centralName;
        config([
            'database.connections.fixture_central' => $connection,
            'tenancy.database.central_connection' => 'fixture_central',
            'tenancy.database.prefix' => $prefix, 'tenancy.database.suffix' => '',
        ]);
        DB::setDefaultConnection('fixture_central');
        $this->artisan('migrate', ['--database' => 'fixture_central', '--path' => database_path('migrations/central'), '--realpath' => true, '--no-interaction' => true])->assertSuccessful();
        $this->seed(LocalDevelopmentSeeder::class);
        expect(User::count())->toBe(3)->and(Tenant::count())->toBe(2);
        foreach (Tenant::with('owner')->get() as $tenant) {
            $tenant->run(function () use ($tenant): void {
                $db = DB::connection('tenant');
                expect($db->table('users')->where('central_user_uuid', $tenant->owner->uuid)->count())->toBe(1);
                expect($db->table('shop')->value('tenant_uuid'))->toBe($tenant->uuid);
                expect($db->table('products')->count())->toBe(20)->and($db->table('orders')->count())->toBe(50);
                $diagnostics = app(DatabaseDiagnostics::class)->read();
                expect($diagnostics['measures'][0]['queries'])->toBe(1)->and($diagnostics['measures'][1]['queries'])->toBe(21);
                expect($diagnostics['plan'])->not->toBeEmpty();
            });
        }
        $this->seed(LocalDevelopmentSeeder::class);
        expect(DB::table('saas_transfers')->count())->toBe(4);
    } finally {
        tenancy()->end();
        DB::purge('fixture_central');
        DB::setDefaultConnection($default);
        config(['tenancy.database' => $original]);
        foreach ([$prefix.'boutique1', $prefix.'boutique2', $centralName] as $name) {
            if (! preg_match('/^aydra_seed_test_[a-f0-9]{12}_(central|boutique[12])$/D', $name)) {
                throw new LogicException('Refusing to drop a database outside this isolated fixture test.');
            }
            DB::connection('fixture_admin')->statement('DROP DATABASE IF EXISTS `'.$name.'`');
        }
        DB::purge('fixture_admin');
    }
})->skip(fn (): bool => getenv('AYDRA_TEST_LOCAL_SEED_MYSQL') !== '1', 'Opt in to isolated fixture databases with AYDRA_TEST_LOCAL_SEED_MYSQL=1.');
