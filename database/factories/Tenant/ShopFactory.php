<?php

namespace Database\Factories\Tenant;

use App\DTOs\Tenant\ShopSettings;
use App\Models\Central\Tenant;
use App\Models\Tenant\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = tenancy()->tenant;

        if (! $tenant instanceof Tenant) {
            throw new LogicException('Shop fixtures require an initialized tenant context.');
        }

        return [
            ...ShopSettings::fromArray()->toArray(),
            'tenant_uuid' => $tenant->uuid,
            'singleton' => 1,
            'central_profile_version' => $tenant->profile_version,
            'shop_name' => $tenant->shop_name,
            'locale' => $tenant->owner->locale,
            'currency' => 'DZD',
            'timezone' => 'Africa/Algiers',
        ];
    }
}
