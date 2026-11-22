<?php

namespace Database\Seeders;

use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CartLifetimeFeatureSeeder;
use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\DomainQuotaFeatureSeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Database\Seeders\Central\GeographicAreaSeeder;
use Database\Seeders\Central\LocalAuthenticationSeeder;
use Database\Seeders\Central\LocalDevelopmentSeeder;
use Database\Seeders\Central\SamexGeoMappingSeeder;
use Database\Seeders\Central\SamexShippingRateSeeder;
use Database\Seeders\Central\ShippingCarrierSeeder;
use Database\Seeders\Central\TenantTeamQuotaFeatureSeeder;
use Illuminate\Database\Seeder;
use LogicException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Central seeders require the central context.');
        }

        $this->call(CountrySeeder::class);
        $this->call(ShippingCarrierSeeder::class);
        $this->call(GeographicAreaSeeder::class);
        $this->call(SamexGeoMappingSeeder::class);
        $this->call(SamexShippingRateSeeder::class);
        $this->call(FreePlanSeeder::class);
        $this->call(TenantTeamQuotaFeatureSeeder::class);
        $this->call(DomainQuotaFeatureSeeder::class);
        $this->call(CartLifetimeFeatureSeeder::class);
        $this->call(AuthorizationSeeder::class);

        $this->call(LocalDevelopmentSeeder::class);
        $this->call(LocalAuthenticationSeeder::class);
    }
}
