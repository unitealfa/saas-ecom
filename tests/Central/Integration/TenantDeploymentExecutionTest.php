<?php

use App\Enums\Central\Deployments\StatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\VerificationStatusEnum;
use App\Jobs\Central\MigrateTenantJob;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Tenant\Shop;
use App\Models\User;
use App\Services\Central\Tenants\TenantDeploymentService;
use App\Services\Central\Tenants\TenantProvisioner;
use App\Services\Central\Tenants\TenantSchemaVersions;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('a real queued migration recovers from failure without losing its history or crossing databases', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));
    $owner = User::factory()->create(['legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => $admin->id]);
    $database = null;
    try {
        $this->actingAs($admin, 'central')->post(route('central.users.tenants.store', $owner), [
            'shop_name' => 'Deployment integration', 'slug' => 'deployment-integration', 'creation_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $tenant = Tenant::where('slug', 'deployment-integration')->firstOrFail();
        $database = $tenant->database()->getName();
        Artisan::call('queue:work', ['connection' => 'central-provisioning', '--stop-when-empty' => true, '--no-interaction' => true]);
        expect($tenant->refresh()->status)->toBe(TenantStatus::ACTIVE);
        expect(TenantSchemaDeployment::where('tenant_id', $tenant->id)->firstOrFail()->status)->toBe(StatusEnum::SUCCEEDED);
        $tenant->run(fn () => expect(Schema::hasColumn('shop', 'deployment_probe'))->toBeFalse());

        app()->instance(TenantSchemaVersions::class, new class extends TenantSchemaVersions
        {
            public function files(): array
            {
                return [...parent::files(), base_path('tests/Fixtures/TenantMigrations/2026_10_26_192213_add_deployment_probe_to_shop_table.php')];
            }
        });
        $service = app(TenantDeploymentService::class);
        $latest = TenantSchemaDeployment::where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
        $realQueue = Queue::getFacadeRoot();
        Queue::fake();
        $migration = $service->migrate($admin, $tenant, app(TenantSchemaVersions::class)->latest(), $latest->managementState());
        expect($tenant->refresh()->status)->toBe(TenantStatus::PROVISIONING);
        expect(tenancy()->initialized)->toBeFalse();
        $this->get('http://deployment-integration.'.config('tenancy.saas_base_domain').'/')->assertServiceUnavailable();

        config(['database.connections.deployment_competitor' => config('database.connections.mysql')]);
        $competitor = DB::connection('deployment_competitor');
        $lock = 'aydra:provision:'.hash('sha1', DB::connection()->getDatabaseName().':'.$tenant->id);
        expect((int) $competitor->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired)->toBe(1);
        try {
            $contended = (new MigrateTenantJob($migration->id))->withFakeQueueInteractions();
            app()->call([$contended, 'handle']);
            $contended->assertReleased(15);
            expect($migration->refresh()->status)->toBe(StatusEnum::PENDING);
        } finally {
            $competitor->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
            DB::disconnect('deployment_competitor');
        }

        $broken = Mockery::mock(TenantProvisioner::class);
        $broken->shouldReceive('migrateExisting')->once()->andThrow(new LogicException('private-migration-secret'));
        expect(fn () => app()->call([new MigrateTenantJob($migration->id), 'handle'], ['provisioner' => $broken]))
            ->toThrow(LogicException::class, 'private-migration-secret');
        expect($migration->refresh()->status)->toBe(StatusEnum::FAILED);
        expect($migration->sanitized_error)->not->toContain('private-migration-secret');
        expect($tenant->refresh()->status)->toBe(TenantStatus::PROVISIONING_FAILED);
        expect(tenancy()->initialized)->toBeFalse();

        $secondFailure = Mockery::mock(TenantProvisioner::class);
        $secondFailure->shouldReceive('migrateExisting')->once()->andThrow(new LogicException('second-private-failure'));
        expect(fn () => app()->call([new MigrateTenantJob($migration->id), 'handle'], ['provisioner' => $secondFailure]))
            ->toThrow(LogicException::class, 'second-private-failure');
        $automatic = TenantSchemaDeployment::where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
        expect($automatic->attempt_number)->toBe(2);
        expect($automatic->status)->toBe(StatusEnum::FAILED);

        $retry = $service->retry($admin, $automatic, $automatic->managementState());
        expect($retry->attempt_number)->toBe(3);
        expect($migration->refresh()->status)->toBe(StatusEnum::FAILED);
        expect($retry->correlation_id)->toBe($migration->correlation_id);
        Queue::swap($realQueue);
        MigrateTenantJob::dispatch($retry->id)->beforeCommit();
        Artisan::call('queue:work', ['connection' => 'central-provisioning', '--stop-when-empty' => true, '--no-interaction' => true]);

        expect($retry->refresh()->status)->toBe(StatusEnum::SUCCEEDED);
        expect($migration->refresh()->status)->toBe(StatusEnum::FAILED);
        expect($tenant->refresh()->status)->toBe(TenantStatus::ACTIVE);
        expect($tenant->schema_version)->toBe('2026_10_26_192213_add_deployment_probe_to_shop_table');
        expect(tenancy()->initialized)->toBeFalse();
        expect(DB::getDefaultConnection())->toBe(config('tenancy.database.central_connection'));
        expect(Schema::hasTable('shop'))->toBeFalse();
        $tenant->run(function () use ($tenant, $owner): void {
            expect(Schema::hasColumn('shop', 'deployment_probe'))->toBeTrue();
            expect(Shop::count())->toBe(1);
            expect(Shop::firstOrFail()->tenant_uuid)->toBe($tenant->uuid);
            expect(App\Models\Tenant\User::where('central_user_uuid', $owner->uuid)->count())->toBe(1);
        });
        $count = TenantSchemaDeployment::count();
        app()->call([new MigrateTenantJob($migration->id), 'handle']);
        expect(TenantSchemaDeployment::count())->toBe($count);
        expect(DB::table('jobs')->count())->toBe(0);
        expect(DB::table('failed_jobs')->count())->toBe(0);
    } finally {
        tenancy()->end();
        if ($database !== null) {
            TestCase::dropIsolatedMysqlDatabase($database);
        }
    }
});
