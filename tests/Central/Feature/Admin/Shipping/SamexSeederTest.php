<?php

use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Shipping\ShippingRate;
use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\GeographicAreaSeeder;
use Database\Seeders\Central\SamexGeoMappingSeeder;
use Database\Seeders\Central\SamexShippingRateSeeder;
use Database\Seeders\Central\ShippingCarrierSeeder;

test('Samex seeds active references once and preserves subsequent administrative deactivations', function (): void {
    $this->seed([CountrySeeder::class, ShippingCarrierSeeder::class, GeographicAreaSeeder::class,
        SamexGeoMappingSeeder::class, SamexShippingRateSeeder::class]);
    $carrier = ShippingCarrier::where('code', ShippingCarrierSeeder::SAMEX)->sole();
    expect($carrier->is_active)->toBeTrue()
        ->and(CarrierGeoMapping::where('is_active', true)->count())->toBe(56)
        ->and(ShippingRate::where('is_active', true)->count())->toBe(544);

    $mapping = CarrierGeoMapping::firstOrFail();
    $rate = ShippingRate::firstOrFail();
    $mapping->forceFill(['is_active' => false, 'mapping_version' => 2])->save();
    $carrier->forceFill(['is_active' => false, 'reference_version' => 2])->save();
    $rate->forceFill(['is_active' => false])->save();
    $this->seed([ShippingCarrierSeeder::class, SamexGeoMappingSeeder::class, SamexShippingRateSeeder::class]);

    expect($carrier->refresh()->is_active)->toBeFalse()
        ->and($mapping->refresh()->is_active)->toBeFalse()
        ->and($rate->refresh()->is_active)->toBeFalse()
        ->and(CarrierGeoMapping::count())->toBe(56)
        ->and(ShippingRate::count())->toBe(544);
});

test('Samex seed repairs untouched initial inactive mappings without changing archived ones', function (): void {
    $this->seed([CountrySeeder::class, ShippingCarrierSeeder::class, GeographicAreaSeeder::class, SamexGeoMappingSeeder::class]);
    $mapping = CarrierGeoMapping::firstOrFail();
    $mapping->forceFill(['is_active' => false, 'mapping_version' => 1, 'verified_at' => null])->save();
    $this->seed(SamexGeoMappingSeeder::class);
    expect($mapping->refresh()->is_active)->toBeTrue()
        ->and($mapping->mapping_version)->toBe(2)
        ->and($mapping->verified_at)->not->toBeNull();
});
