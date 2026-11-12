<?php

namespace Database\Seeders\Central;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Shipping\ShippingCarrier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class SamexGeoMappingSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Samex seeding requires the central context.');
        }

        $rows = json_decode(file_get_contents(database_path('data/shipping/samex-wilayas.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($rows): void {
            $carrier = ShippingCarrier::withTrashed()->where('code', ShippingCarrierSeeder::SAMEX)->lockForUpdate()->sole();
            if ($carrier->trashed()) {
                return;
            }
            $country = Country::where('code', 'DZ')->lockForUpdate()->sole();
            $areas = GeographicArea::withTrashed()->where('country_id', $country->id)
                ->where('type', GeoZoneTypeEnum::PROVINCE)->lockForUpdate()->get()
                ->keyBy(fn (GeographicArea $area): int => (int) $area->code);

            foreach ($rows as $row) {
                $area = $areas->get((int) $row['code']);
                if ($area === null) {
                    throw new LogicException('Wilaya absente du référentiel : '.$row['code']);
                }

                $active = $carrier->is_active && $area->is_active && ! $area->trashed();
                $existing = CarrierGeoMapping::where('carrier_id', $carrier->id)->where('geographic_area_id', $area->id)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ($active && ! $existing->is_active && $existing->mapping_version === 1
                        && $existing->verified_at === null
                        && $existing->verification_source === 'lefichiers.txt — grille Samex fournie') {
                        $existing->forceFill(['is_active' => true, 'mapping_version' => 2, 'verified_at' => now()])->save();
                    }

                    continue;
                }

                $mapping = new CarrierGeoMapping;
                $mapping->forceFill([
                    'carrier_id' => $carrier->id,
                    'geographic_area_id' => $area->id,
                    'zone_type' => GeoZoneTypeEnum::PROVINCE,
                    'external_code' => $row['code'],
                    'external_name' => $row['name'],
                    'external_province_code' => $row['code'],
                    'verification_source' => 'lefichiers.txt — grille Samex fournie',
                    'mapping_version' => 1,
                    'is_active' => $active,
                    'verified_at' => $active ? now() : null,
                ])->save();
            }
        }, 3);
    }
}
