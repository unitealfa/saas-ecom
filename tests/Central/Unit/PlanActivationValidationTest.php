<?php

use App\Services\Central\Plans\PlanActivationService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Validator;

pest()->extend(TestCase::class);

test('activation input requires a boolean and valid state without allowing unrelated changes', function (): void {
    $data = ['expected_state' => str_repeat('a', 64), 'is_active' => false];
    expect(Validator::make($data, PlanActivationService::rules())->passes())->toBeTrue();
    expect(Validator::make([...$data, 'is_active' => 'true'], PlanActivationService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$data, 'expected_state' => 'stale'], PlanActivationService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$data, 'monthly_price' => '1.00'], PlanActivationService::rules())->fails())->toBeTrue();
});
