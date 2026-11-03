<?php

use App\Services\Central\Plans\PlanUpdateService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Validator;

pest()->extend(TestCase::class);

test('plan update validates prices and rejects protected fields and stale tokens', function (): void {
    $data = ['expected_state' => str_repeat('a', 64), 'name' => 'Pro', 'description' => null, 'monthly_price' => '10.00', 'annual_price' => '100.00'];
    expect(Validator::make($data, PlanUpdateService::rules())->passes())->toBeTrue();
    expect(Validator::make([...$data, 'monthly_price' => '-1'], PlanUpdateService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$data, 'code' => 'other'], PlanUpdateService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$data, 'expected_state' => 'stale'], PlanUpdateService::rules())->fails())->toBeTrue();
});
