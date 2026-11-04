<?php

namespace Database\Seeders\Central;

use App\Models\Central\Countries\Country;
use Illuminate\Database\Seeder;
use LogicException;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Countries must be seeded in the central context.');
        }

        foreach ([
            ['DZ', 'Algérie', 'Algeria', 'الجزائر', true],
            ['FR', 'France', 'France', 'فرنسا', false],
            ['SA', 'Arabie saoudite', 'Saudi Arabia', 'السعودية', false],
            ['SD', 'Soudan', 'Sudan', 'السودان', false],
            ['EG', 'Égypte', 'Egypt', 'مصر', false],
        ] as [$code, $french, $english, $arabic, $active]) {
            Country::withTrashed()->firstOrCreate(
                ['code' => $code],
                [
                    'name_fr' => $french,
                    'name_en' => $english,
                    'name_ar' => $arabic,
                    'is_active' => $active,
                ]
            );
        }
    }
}
