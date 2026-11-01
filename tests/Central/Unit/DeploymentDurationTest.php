<?php

use App\Models\Central\Deployments\TenantSchemaDeployment;
use Illuminate\Foundation\Testing\TestCase;

pest()->extend(TestCase::class);

test('deployment duration describes the real attempt interval', function (?string $start, ?string $end, string $expected): void {
    $deployment = new TenantSchemaDeployment;
    $deployment->setRawAttributes(['started_at' => $start, 'ended_at' => $end]);

    expect($deployment->durationLabel())->toBe($expected);
})->with([
    'pending' => [null, null, 'Pas encore démarré'],
    'running' => ['2026-11-01 10:00:00', null, 'En cours'],
    'seconds' => ['2026-11-01 10:00:00', '2026-11-01 10:00:05', '5 s'],
    'minutes' => ['2026-11-01 10:00:00', '2026-11-01 10:01:05', '1 min 5 s'],
    'hours' => ['2026-11-01 10:00:00', '2026-11-01 11:02:03', '1 h 2 min 3 s'],
    'zero duration' => ['2026-11-01 10:00:00', '2026-11-01 10:00:00', '0 s'],
]);
