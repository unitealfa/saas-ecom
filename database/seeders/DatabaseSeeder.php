<?php

namespace Database\Seeders;

use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Database\Seeders\Central\GeographicAreaSeeder;
use Database\Seeders\Central\LocalDevelopmentSeeder;
use Database\Seeders\Central\ShippingCarrierSeeder;
use Database\Seeders\Central\LocalAuthenticationSeeder;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CountrySeeder::class);
        $this->call(ShippingCarrierSeeder::class);
        $this->call(GeographicAreaSeeder::class);
        $this->call(FreePlanSeeder::class);
        $this->call(LocalDevelopmentSeeder::class);
        $this->call(LocalAuthenticationSeeder::class);
        $this->call(CountrySeeder::class);
        $this->call(AuthorizationSeeder::class);
    }
}