<?php

namespace Database\Seeders\Central;

use App\Services\Central\Geography\GeographyImportService;
use Illuminate\Database\Seeder;

class GeographicAreaSeeder extends Seeder
{
    public function run(GeographyImportService $importer): void
    {
        $importer->import($importer->loadLocal());
    }
}
