<?php

use App\Services\Central\Geography\GeographyImportService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    DB::connection()->beforeExecuting(function (): void {
        throw new LogicException('La validation géographique ne doit pas exécuter de SQL.');
    });
});

function geographyImportPayload(): array
{
    return [
        'wilayas' => [['id' => 73, 'code' => 73, 'name' => 'Wilaya exemple', 'ar_name' => 'ولاية', 'parent_id' => 42]],
        'communes' => [['id' => '901', 'wilaya_id' => '73', 'name' => 'Commune exemple', 'ar_name' => 'بلدية', 'post_code' => '73001']],
    ];
}

test('local geography has consistent source identifiers and factual metadata', function (): void {
    $service = app(GeographyImportService::class);
    $bundle = $service->loadLocal();
    $data = $service->validatePayload($bundle);

    expect($data['wilayas'])->toHaveCount(69)
        ->and($data['communes'])->toHaveCount(1541)
        ->and($data['metadata']['source'])->toBe('local-json:database/data/geography/dz')
        ->and($data['metadata']['version'])->toStartWith('sha256:')
        ->and($data['metadata']['effective_at'])->toBeNull()
        ->and(array_column($data['communes'], 'code'))->toHaveCount(1541)
        ->and(array_unique(array_column($data['communes'], 'code')))->toHaveCount(1541);
});

test('normalization maps external commune codes without retaining historical wilaya parents', function (): void {
    $data = app(GeographyImportService::class)->validatePayload(geographyImportPayload());

    expect($data['wilayas'][0])->not->toHaveKey('parent_id')
        ->and($data['wilayas'][0]['code'])->toBe('73')
        ->and($data['wilayas'][0]['name_ar'])->toBe('ولاية')
        ->and($data['communes'][0]['code'])->toBe('dz-73001')
        ->and($data['communes'][0]['name_ar'])->toBe('بلدية')
        ->and($data['communes'][0]['wilaya_id'])->toBe(73)
        ->and($data['metadata']['source'])->toBe('seed-json')
        ->and($data['metadata']['effective_at'])->toBeNull();
});

test('default source version changes when uploaded data changes', function (): void {
    $service = app(GeographyImportService::class);
    $payload = geographyImportPayload();
    $before = $service->validatePayload($payload);
    $payload['communes'][0]['name'] = 'Autre nom';
    $after = $service->validatePayload($payload);

    expect($before['metadata']['version'])->not->toBe($after['metadata']['version']);
});

test('invalid geography bundles are rejected before database access', function (Closure $change): void {
    $payload = geographyImportPayload();
    $change($payload);

    expect(fn () => app(GeographyImportService::class)->validatePayload($payload))
        ->toThrow(ValidationException::class);
})->with([
    'unknown parent' => [function (array &$payload): void {
        $payload['communes'][0]['wilaya_id'] = 999;
    }],
    'duplicate source ID' => [function (array &$payload): void {
        $payload['communes'][] = $payload['communes'][0];
    }],
    'duplicate province code after normalization' => [function (array &$payload): void {
        $payload['wilayas'][] = ['id' => 74, 'code' => ' 73 ', 'name' => 'Autre'];
    }],
    'duplicate municipality postal code' => [function (array &$payload): void {
        $payload['communes'][] = [...$payload['communes'][0], 'id' => 902, 'name' => 'Autre commune'];
    }],
    'missing postal code' => [function (array &$payload): void {
        unset($payload['communes'][0]['post_code']);
    }],
    'malformed postal code' => [function (array &$payload): void {
        $payload['communes'][0]['post_code'] = 'non postal';
    }],
    'malformed list' => [function (array &$payload): void {
        $payload['communes'] = 'incorrect';
    }],
    'malformed row' => [function (array &$payload): void {
        $payload['communes'][0] = 'incorrect';
    }],
    'invalid source' => [function (array &$payload): void {
        $payload['metadata'] = ['source' => ' '];
    }],
    'invalid effective date' => [function (array &$payload): void {
        $payload['metadata'] = ['effective_at' => '2026-02-31'];
    }],
    'malformed metadata' => [function (array &$payload): void {
        $payload['metadata'] = 'incorrect';
    }],
]);

test('declared optional provenance is preserved without inventing an effective date', function (): void {
    $payload = geographyImportPayload();
    $payload['metadata'] = ['source' => 'Source déclarée', 'version' => 'version-test', 'effective_at' => null];
    $data = app(GeographyImportService::class)->validatePayload($payload);

    expect($data['metadata'])->toBe($payload['metadata']);
});

test('commune codes preserve the supplied postal code independently of source IDs', function (): void {
    $payload = geographyImportPayload();
    $payload['communes'][0]['post_code'] = '01001';
    $payload['communes'][] = [...$payload['communes'][0], 'id' => 902, 'post_code' => '1002'];
    $data = app(GeographyImportService::class)->validatePayload($payload);

    expect(array_column($data['communes'], 'code'))->toBe(['dz-01001', 'dz-1002']);
    $payload['communes'][0]['id'] = 990;
    expect(app(GeographyImportService::class)->validatePayload($payload)['communes'][0]['code'])->toBe('dz-01001');
});

test('a postal code can be reused by a commune of another wilaya', function (): void {
    $payload = geographyImportPayload();
    $payload['wilayas'][] = ['id' => 74, 'code' => 74, 'name' => 'Autre wilaya'];
    $payload['communes'][] = [...$payload['communes'][0], 'id' => 902, 'wilaya_id' => 74];

    expect(app(GeographyImportService::class)->validatePayload($payload)['communes'])->toHaveCount(2);
});

test('geography seeding rejects a tenant context before querying the database', function (): void {
    tenancy()->initialized = true;
    try {
        expect(fn () => app(GeographyImportService::class)->import(geographyImportPayload()))
            ->toThrow(LogicException::class, 'contexte central');
    } finally {
        tenancy()->initialized = false;
    }
});
