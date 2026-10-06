<?php

namespace Database\Seeders\Central;

use App\Models\Central\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['DZ', 'Algérie', 'Algeria', true],
            ['FR', 'France', 'France', false],
            ['SA', 'Arabie saoudite', 'Saudi Arabia', false],
            ['SD', 'Soudan', 'Sudan', false],
            ['EG', 'Égypte', 'Egypt', false],
        ] as [$code, $french, $english, $active]) {
            Country::firstOrCreate(['code' => $code], [
                'name_fr' => $french,
                'name_en' => $english,
                'is_active' => $active,
            ]);
        }
    }
}
