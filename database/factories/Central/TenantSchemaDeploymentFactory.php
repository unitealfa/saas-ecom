<?php

namespace Database\Factories\Central;

use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TenantSchemaDeployment> */
class TenantSchemaDeploymentFactory extends Factory
{
    protected $model = TenantSchemaDeployment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'target_version' => 'test-schema', 'operation' => 1, 'status' => 1, 'attempt_number' => 1, 'operation_key' => (string) Str::uuid(), 'correlation_id' => (string) Str::uuid(), 'runtime_versions' => ['php' => PHP_VERSION]];
    }
}
