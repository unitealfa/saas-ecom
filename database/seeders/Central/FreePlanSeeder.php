<?php

namespace Database\Seeders\Central;

use App\Services\Central\FreePlanCatalog;
use Illuminate\Database\Seeder;

class FreePlanSeeder extends Seeder
{
    public function run(): void
    {
        app(FreePlanCatalog::class)->current();
    }
}
