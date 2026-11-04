<?php

namespace Database\Seeders\Central;

use App\Services\Central\Roles\CentralRoleCatalog;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        app(CentralRoleCatalog::class)->seed();
    }
}