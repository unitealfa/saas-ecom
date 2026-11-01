<?php

namespace Database\Seeders\Central;

use App\Models\Central\Shipping\ShippingCarrier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class ShippingCarrierSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Carrier seeding requires the central context.');
        }

        DB::transaction(function (): void {
            ShippingCarrier::unguarded(fn () => ShippingCarrier::withTrashed()->firstOrCreate(
                ['code' => ShippingCarrier::YALIDINE],
                [
                    'name' => 'Yalidine', 'adapter' => ShippingCarrier::YALIDINE,
                    'default_api_url' => null, 'is_active' => false,
                    'reference_source' => ShippingCarrier::YALIDINE_SOURCE, 'reference_version' => 1,
                ],
            ));
        });
    }
}
