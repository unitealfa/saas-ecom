<?php

namespace Database\Factories\Central;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Shipping\PickupPoint;
use App\Models\Central\Shipping\ShippingCarrier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PickupPoint> */
class PickupPointFactory extends Factory
{
    protected $model = PickupPoint::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'carrier_id' => ShippingCarrier::factory(),
            'province_id' => function (): int {
                $country = Country::where('code', 'DZ')->first() ?? Country::factory()->create(['code' => 'DZ', 'is_active' => true]);
                $area = new GeographicArea;
                $area->forceFill(['country_id' => $country->id, 'type' => GeoZoneTypeEnum::PROVINCE,
                    'code' => fake()->unique()->numerify('TEST-######'), 'name_fr' => 'Wilaya de test',
                    'is_active' => true, 'reference_source' => 'test', 'reference_version' => 'test-1'])->save();

                return $area->id;
            },
            'external_code' => fake()->unique()->numerify('TEST-######'),
            'name' => 'Bureau de test', 'address' => 'Adresse de test',
            'phone' => null, 'map_url' => null, 'is_carrier_active' => false,
            'reference_source' => 'test', 'reference_version' => 1, 'synced_at' => null,
        ];
    }
}
