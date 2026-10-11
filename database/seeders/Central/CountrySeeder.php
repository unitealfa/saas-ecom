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
            ['DZ', 'Algérie', 'Algeria', 'الجزائر', true],
            ['FR', 'France', 'France', 'فرنسا', false],
            ['SA', 'Arabie saoudite', 'Saudi Arabia', 'السعودية', false],
            ['SD', 'Soudan', 'Sudan', 'السودان', false],
            ['EG', 'Égypte', 'Egypt', 'مصر', false],
        ] as [$code, $french, $english, $arabic, $active]) {
            Country::withTrashed()->firstOrCreate(['code' => $code], [
                'name_fr' => $french,
                'name_en' => $english,
                'name_ar' => $arabic,
                'is_active' => $active,
            ]);
        }
    }
}
