<?php

use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Central\User;
use App\Services\Central\Tenants\TenantProvisioner;
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
