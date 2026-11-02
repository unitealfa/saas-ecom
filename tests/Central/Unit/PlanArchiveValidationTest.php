<?php

use App\Services\Central\Plans\PlanArchiveService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Validator;

pest()->extend(TestCase::class);

test('archive requires a valid state and rejects unrelated mutations', function (): void {
    $data = ['expected_state' => str_repeat('a', 64)];
    expect(Validator::make($data, PlanArchiveService::rules())->passes())->toBeTrue();
    expect(Validator::make(['expected_state' => 'stale'], PlanArchiveService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$data, 'is_active' => true], PlanArchiveService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$data, 'features' => [['limit' => 1]]], PlanArchiveService::rules())->fails())->toBeTrue();
});
