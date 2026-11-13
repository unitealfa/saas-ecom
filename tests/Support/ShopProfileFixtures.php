<?php

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;

/** @return array{province_uuid: string, municipality_uuid: string} */
function shopProfileGeography(): array
{
    $country = Country::where('code', 'DZ')->first() ?? Country::factory()->create(['code' => 'DZ', 'is_active' => true]);
    $areas = [];
    foreach ([GeoZoneTypeEnum::PROVINCE, GeoZoneTypeEnum::MUNICIPALITY] as $type) {
        $code = $type === GeoZoneTypeEnum::PROVINCE ? '16' : 'dz-16000';
        $area = GeographicArea::where('country_id', $country->id)->where('type', $type)->where('code', $code)->first();
        if ($area === null) {
            $area = new GeographicArea;
            $area->forceFill([
                'country_id' => $country->id, 'parent_id' => $areas['province_uuid']->id ?? null,
                'type' => $type, 'code' => $code, 'name_fr' => 'Zone de test '.$code,
                'is_active' => true, 'reference_source' => 'test-fixture', 'reference_version' => 'test-1',
            ])->save();
        }
        $areas[$type === GeoZoneTypeEnum::PROVINCE ? 'province_uuid' : 'municipality_uuid'] = $area;
    }

    return ['province_uuid' => $areas['province_uuid']->uuid, 'municipality_uuid' => $areas['municipality_uuid']->uuid];
}
