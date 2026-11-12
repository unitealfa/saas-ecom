<?php

namespace Database\Seeders\Central;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Shipping\ShippingRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use LogicException;

class SamexShippingRateSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Samex seeding requires the central context.');
        }

        $rows = File::json(database_path('data/shipping/samex-wilayas.json'));
        $rateRules = [];
        foreach (ShippingRate::SERVICES as $service) {
            $rateRules['rows.*.rates.'.$service] = ['sometimes', 'array:home,office'];
            foreach (ShippingRate::MODES as $mode) {
                $rateRules['rows.*.rates.'.$service.'.'.$mode] = ['sometimes', 'integer', 'min:0'];
            }
        }
        Validator::make(['rows' => $rows], [
            'rows' => ['required', 'array', 'list'],
            'rows.*.code' => ['required', 'integer', 'min:1', 'distinct'],
            'rows.*.rates' => ['required', 'array:'.implode(',', ShippingRate::SERVICES)],
            ...$rateRules,
        ])->validate();

        DB::transaction(function () use ($rows): void {
            $carrier = ShippingCarrier::withTrashed()->where('code', ShippingCarrierSeeder::SAMEX)->lockForUpdate()->sole();
            if ($carrier->trashed()) {
                return;
            }
            $country = Country::where('code', 'DZ')->lockForUpdate()->sole();
            $areas = GeographicArea::where('country_id', $country->id)
                ->where('type', GeoZoneTypeEnum::PROVINCE)->lockForUpdate()->get()
                ->keyBy(fn (GeographicArea $area): int => (int) $area->code);
            $existing = ShippingRate::where('carrier_id', $carrier->id)->lockForUpdate()->get()
                ->keyBy(fn (ShippingRate $rate): string => $rate->getAttribute('geographic_area_id').':'.$rate->getAttribute('service').':'.$rate->getAttribute('delivery_mode'));

            foreach ($rows as $row) {
                $area = $areas->get((int) $row['code']);
                if ($area === null) {
                    throw new LogicException('Wilaya absente du référentiel : '.$row['code']);
                }

                foreach ($row['rates'] as $service => $modes) {
                    foreach ($modes as $mode => $amount) {
                        if ($existing->has($area->id.':'.$service.':'.$mode)) {
                            continue;
                        }
                        $rate = new ShippingRate;
                        $rate->forceFill([
                            'carrier_id' => $carrier->id,
                            'geographic_area_id' => $area->id,
                            'service' => $service,
                            'delivery_mode' => $mode,
                            'amount' => $amount,
                            'currency' => 'DZD',
                            'reference_source' => 'lefichiers.txt — grille Samex fournie',
                            'is_active' => false,
                        ])->save();
                    }
                }
            }
        }, 3);
    }
}
