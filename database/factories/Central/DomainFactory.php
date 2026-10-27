<?php

namespace Database\Factories\Central;

use App\Models\Central\Domains\Domain;
use App\Models\Central\Tenants\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'domain' => Str::lower(Str::random(16)).'.aydra.localhost',
            'type' => 1,
            'is_primary' => false,
            'verification_status' => 1,
        ];
    }
}
