<?php

namespace Database\Factories\Central;

use App\Models\Central\Shipping\ShippingCarrier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShippingCarrier> */
class ShippingCarrierFactory extends Factory
{
    protected $model = ShippingCarrier::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => ShippingCarrier::YALIDINE,
            'name' => 'Yalidine',
            'adapter' => ShippingCarrier::YALIDINE,
            'default_api_url' => null,
            'is_active' => false,
            'reference_source' => ShippingCarrier::YALIDINE_SOURCE,
            'reference_version' => 1,
            'synced_at' => null,
        ];
    }
}
