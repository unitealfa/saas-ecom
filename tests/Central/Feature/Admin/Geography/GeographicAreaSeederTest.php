<?php

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Services\Central\Geography\GeographyImportService;
use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\GeographicAreaSeeder;
use Illuminate\Validation\ValidationException;

test('the complete local geography seed preserves identities states and manual corrections', function (): void {
    $this->seed([CountrySeeder::class, GeographicAreaSeeder::class]);
    $service = app(GeographyImportService::class);
    $payload = $service->loadLocal();
    $areas = GeographicArea::orderBy('id')->get();
    $country = Country::where('code', 'DZ')->firstOrFail();
    $provinces = $areas->where('type', GeoZoneTypeEnum::PROVINCE)->keyBy('code');
    $municipalities = $areas->where('type', GeoZoneTypeEnum::MUNICIPALITY)->keyBy('code');
    $parentCodes = array_column($payload['wilayas'], 'code', 'id');

    expect($provinces)->toHaveCount(69)
        ->and($municipalities)->toHaveCount(1541)
        ->and($areas->pluck('country_id')->unique()->all())->toBe([$country->id])
        ->and($provinces->pluck('parent_id')->unique()->all())->toBe([null]);

    foreach ($payload['communes'] as $row) {
        expect($municipalities['dz-'.$row['post_code']]->parent_id)
            ->toBe($provinces[$parentCodes[$row['wilaya_id']]]->id);
    }

    $identities = $areas->map(fn (GeographicArea $area): array => [$area->id, $area->uuid])->all();
    $disabled = $municipalities->values()[0];
    $archived = $municipalities->values()[1];
    $disabled->forceFill(['is_active' => false])->save();
    $archived->delete();
    $archivedAt = $archived->deleted_at->toDateTimeString();

    $this->seed(GeographicAreaSeeder::class);

    expect(GeographicArea::withTrashed()->count())->toBe(1610)
        ->and(GeographicArea::withTrashed()->orderBy('id')->get()->map(fn (GeographicArea $area): array => [$area->id, $area->uuid])->all())->toBe($identities)
        ->and($disabled->refresh()->is_active)->toBeFalse()
        ->and($archived->refresh()->deleted_at->toDateTimeString())->toBe($archivedAt)
        ->and($service->import($payload)['ignored'])->toBe(1610);

    $disabled->update(['name_fr' => 'Correction administrative conservée']);
    expect(fn () => $this->seed(GeographicAreaSeeder::class))->toThrow(ValidationException::class);
    expect($disabled->refresh()->name_fr)->toBe('Correction administrative conservée')
        ->and($disabled->is_active)->toBeFalse()
        ->and($archived->refresh()->trashed())->toBeTrue()
        ->and(GeographicArea::withTrashed()->count())->toBe(1610);
});

test('imports keep homonymous communes and scope postal codes to their parent wilaya', function (): void {
    $this->seed(CountrySeeder::class);
    $service = app(GeographyImportService::class);
    $payload = ['wilayas' => [
        ['id' => 1, 'code' => 1, 'name' => 'Wilaya A'],
        ['id' => 2, 'code' => 2, 'name' => 'Wilaya B'],
    ], 'communes' => [
        ['id' => 10, 'wilaya_id' => 1, 'name' => 'Même nom', 'post_code' => '1001'],
        ['id' => 11, 'wilaya_id' => 1, 'name' => 'Même nom', 'post_code' => '1002'],
        ['id' => 12, 'wilaya_id' => 2, 'name' => 'Même nom', 'post_code' => '1001'],
    ]];

    expect($service->import($payload)['created'])->toBe(5);
    $identities = GeographicArea::orderBy('id')->pluck('uuid', 'id')->all();
    expect($service->import($payload)['ignored'])->toBe(5)
        ->and($service->import($payload)['created'])->toBe(0)
        ->and(GeographicArea::where('type', 2)->where('code', 'dz-1001')->count())->toBe(2)
        ->and(GeographicArea::orderBy('id')->pluck('uuid', 'id')->all())->toBe($identities);
});
