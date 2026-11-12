<?php

namespace Database\Factories\Central;

use App\Models\Central\Shipping\ShippingCarrier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ShippingCarrier> */
class ShippingCarrierFactory extends Factory
{
    protected $model = ShippingCarrier::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => 'carrier-'.Str::lower(Str::random(16)),
            'name' => fake()->company(),
            'adapter' => ShippingCarrier::UNCONFIGURED_ADAPTER,
            'default_api_url' => null,
            'is_active' => false,
            'reference_source' => 'test-fixture',
            'reference_version' => 1,
            'synced_at' => null,
        ];
    }
}
