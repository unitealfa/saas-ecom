<?php

namespace Database\Seeders\Central;

use App\Models\Central\Shipping\ShippingCarrier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class ShippingCarrierSeeder extends Seeder
{
    public const string SAMEX = 'samex';

    public const string YALIDINE = 'yalidine';

    private const string YALIDINE_SOURCE = 'https://www.yalidine.com/';

    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Carrier seeding requires the central context.');
        }

        DB::transaction(function (): void {
            ShippingCarrier::unguarded(fn () => ShippingCarrier::withTrashed()->firstOrCreate(
                ['code' => self::SAMEX],
                [
                    'name' => 'Samex',
                    'adapter' => ShippingCarrier::UNCONFIGURED_ADAPTER,
                    'default_api_url' => null,
                    'is_active' => false,
                    'reference_source' => 'lefichiers.txt — grille Samex fournie',
                    'reference_version' => 1,
                ]
            ));

            ShippingCarrier::unguarded(fn () => ShippingCarrier::withTrashed()->firstOrCreate(
                ['code' => self::YALIDINE],
                [
                    'name' => 'Yalidine',
                    'adapter' => self::YALIDINE,
                    'default_api_url' => null,
                    'is_active' => false,
                    'reference_source' => self::YALIDINE_SOURCE,
                    'reference_version' => 1,
                ]
            ));
        });
    }
}
