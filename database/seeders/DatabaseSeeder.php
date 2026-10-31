<?php

namespace Database\Seeders;

use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Database\Seeders\Central\LocalAuthenticationSeeder;
use Database\Seeders\Central\LocalDevelopmentSeeder;
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

        if (app()->environment(['local', 'testing'])) {
            $this->call(LocalDevelopmentSeeder::class);
        }

        $this->call(AuthorizationSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(LocalAuthenticationSeeder::class);
        }
    }
}
