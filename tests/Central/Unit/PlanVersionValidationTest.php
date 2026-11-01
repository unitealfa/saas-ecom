<?php

use App\Services\Central\Plans\PlanVersionService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Validator;

pest()->extend(TestCase::class);

test('version input requires a valid state and rejects protected attributes', function (): void {
    $data = ['expected_state' => str_repeat('a', 64), 'expected_latest_version' => 1];
    expect(Validator::make($data, PlanVersionService::rules())->passes())->toBeTrue();
    expect(Validator::make([...$data, 'is_active' => true], PlanVersionService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$data, 'expected_latest_version' => 0], PlanVersionService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$data, 'expected_state' => 'stale'], PlanVersionService::rules())->fails())->toBeTrue();
});
