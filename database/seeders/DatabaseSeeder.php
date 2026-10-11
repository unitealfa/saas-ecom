<?php

namespace Database\Seeders;

use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CountrySeeder::class);
        $this->call(FreePlanSeeder::class);
    }
}
