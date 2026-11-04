<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Seeder;
use LogicException;

class LocalAuthenticationSeeder extends Seeder
{
    public function run(): void
    {

        if (tenancy()->initialized) {
            throw new LogicException('Central context required.');
        }

        $this->command?->info('Authentification : les rôles et permissions sont gérés par AuthorizationSeeder.');
    }
}
