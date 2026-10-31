<?php

namespace Database\Seeders;

use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Database\Seeders\Central\GeographicAreaSeeder;
use Database\Seeders\Central\GeographyPermissionSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CountrySeeder::class);
        $this->call(GeographicAreaSeeder::class);
        $this->call(FreePlanSeeder::class);
        $this->call(GeographyPermissionSeeder::class);
    }
}
