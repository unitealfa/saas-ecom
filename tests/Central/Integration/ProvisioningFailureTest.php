<?php

use App\Enums\Central\Deployments\ErrorCodeEnum;
use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Central\User;
use App\Services\Central\Tenants\TenantProvisioner;
use App\Services\Central\Tenants\TenantSchemaVersions;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('provisioning failures retain the reservation and expose only a sanitized error for recovery', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(CountrySeeder::class);
    Queue::fake();
    Event::fake([TenantCreated::class]);
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $owner->id]);
    $deployment = TenantSchemaDeployment::factory()->create(['tenant_id' => $tenant->id]);
    $provisioner = Mockery::mock(TenantProvisioner::class);
    $provisioner->shouldReceive('provision')->once()->andThrow(new LogicException('private-database-secret'));
    $job = new ProvisionTenantDatabase($deployment->id);

    expect(fn () => app()->call([$job, 'handle'], ['provisioner' => $provisioner]))->toThrow(LogicException::class, 'private-database-secret');
    expect($deployment->fresh()->status)->toBe(DeploymentStatus::FAILED);
    expect($deployment->fresh()->sanitized_error)->not->toContain('private-database-secret');
    expect(Tenant::whereKey($tenant->id)->exists())->toBeTrue();
    $this->artisan('aydra:retry-tenant-provisioning')->assertSuccessful();
    Queue::assertPushed(ProvisionTenantDatabase::class, 1);
});

test('an abandoned running attempt keeps its original history when another worker resumes it', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(CountrySeeder::class);
    Queue::fake();
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create();
    $started = now()->subMinutes(10)->startOfSecond();
    $deployment = TenantSchemaDeployment::factory()->create([
        'tenant_id' => $tenant->id, 'status' => DeploymentStatus::RUNNING, 'started_at' => $started,
        'target_version' => app(TenantSchemaVersions::class)->latest(),
    ]);
    $provisioner = Mockery::mock(TenantProvisioner::class);
    $provisioner->shouldReceive('provision')->once()->andThrow(new LogicException('resumed-step-failed'));
    expect(fn () => app()->call([new ProvisionTenantDatabase($deployment->id), 'handle'], ['provisioner' => $provisioner]))
        ->toThrow(LogicException::class, 'resumed-step-failed');
    expect($deployment->refresh()->status)->toBe(DeploymentStatus::FAILED);
    expect($deployment->error_code)->toBe(ErrorCodeEnum::DEPLOYMENT_EXHAUSTED->value);
    expect($deployment->started_at->equalTo($started))->toBeTrue();
    $retry = TenantSchemaDeployment::where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
    expect($retry->id)->not->toBe($deployment->id);
    expect($retry->status)->toBe(DeploymentStatus::FAILED);
    expect($retry->attempt_number)->toBe(2);
    expect($retry->correlation_id)->toBe($deployment->correlation_id);
    expect(tenancy()->initialized)->toBeFalse();
});
