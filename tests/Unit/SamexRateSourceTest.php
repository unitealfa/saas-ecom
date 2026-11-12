<?php

use App\Models\Central\Shipping\ShippingRate;

test('Samex source preserves known rates and omits unknown returns', function (): void {
    $rows = json_decode(file_get_contents(__DIR__.'/../../database/data/shipping/samex-wilayas.json'), true, flags: JSON_THROW_ON_ERROR);
    $rates = 0;
    $codes = [];
    foreach ($rows as $row) {
        $codes[] = $row['code'];
        foreach ($row['rates'] as $service => $modes) {
            expect(in_array($service, ShippingRate::SERVICES, true))->toBeTrue();
            foreach ($modes as $mode => $amount) {
                expect(in_array($mode, ShippingRate::MODES, true))->toBeTrue();
                expect($amount)->toBeInt()->toBeGreaterThanOrEqual(0);
                $rates++;
            }
        }
    }
    $indexed = array_column($rows, null, 'code');
    expect($rows)->toHaveCount(56)
        ->and(array_unique($codes))->toHaveCount(56)
        ->and($rates)->toBe(544)
        ->and($indexed[16]['rates']['delivery'])->toEqual(['home' => 450, 'office' => 250])
        ->and($indexed[16]['rates']['return'])->toEqual(['home' => 0, 'office' => 0])
        ->and($indexed[49]['rates'])->not->toHaveKey('return');
});
