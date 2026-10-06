<?php

namespace Database\Seeders;

use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\DemoTenantSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CountrySeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoTenantSeeder::class);
        }
    }
}
