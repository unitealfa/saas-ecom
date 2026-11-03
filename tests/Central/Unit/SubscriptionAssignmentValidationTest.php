<?php

use App\Services\Central\Subscriptions\SubscriptionAssignmentService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

pest()->extend(TestCase::class);

test('assignment requires public identifiers and a supported period without accepting server fields', function (): void {
    $input = ['owner_uuid' => (string) Str::uuid(), 'plan_uuid' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(), 'period' => 1, 'expected_amount' => '500.00'];
    expect(Validator::make($input, SubscriptionAssignmentService::rules())->passes())->toBeTrue();
    expect(Validator::make([...$input, 'period' => 9], SubscriptionAssignmentService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$input, 'agreed_amount' => '0'], SubscriptionAssignmentService::rules())->fails())->toBeTrue();
    expect(Validator::make([...$input, 'owner_uuid' => '1'], SubscriptionAssignmentService::rules())->fails())->toBeTrue();
});
