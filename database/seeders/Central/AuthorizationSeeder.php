<?php

namespace Database\Seeders\Central;

use App\Services\Central\CentralRoleCatalog;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(CentralRoleCatalog::class)->seed();
    }
}
