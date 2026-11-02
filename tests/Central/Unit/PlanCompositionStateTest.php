<?php

use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Services\Central\Plans\PlanCompositionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Validator;

pest()->extend(TestCase::class);

test('plan composition state is stable across ordering and changes with the plan or a quota', function (): void {
    $plan = new Plan(['code' => 'pro', 'version' => 1, 'name' => 'Pro', 'monthly_price' => '10.00', 'annual_price' => '100.00', 'is_active' => false]);
    $plan->forceFill(['uuid' => '8fafb845-81e3-4c26-9ed7-bcaefec6579b']);
    $first = new PlanFeature(['feature_id' => 1, 'is_active' => true, 'limit' => 2]);
    $second = new PlanFeature(['feature_id' => 2, 'is_active' => false, 'limit' => null]);
    $state = $plan->compositionState(new Collection([$first, $second]));

    expect($plan->compositionState(new Collection([$second, $first])))->toBe($state);
    $first->limit = 3;
    expect($plan->compositionState(new Collection([$first, $second])))->not->toBe($state);
    $first->limit = 2;
    $plan->name = 'Pro modifié';
    expect($plan->compositionState(new Collection([$first, $second])))->not->toBe($state);
});

test('composition payload validation normalizes UUIDs and rejects duplicates and extra fields', function (): void {
    $uuid = '8fafb845-81e3-4c26-9ed7-bcaefec6579b';
    $data = ['expected_state' => str_repeat('a', 64), 'features' => [
        ['uuid' => strtoupper($uuid), 'is_active' => true, 'limit' => 0],
    ]];
    $normalized = PlanCompositionService::normalize($data);
    expect(Validator::make($normalized, PlanCompositionService::rules())->passes())->toBeTrue();

    $normalized['features'][] = ['uuid' => $uuid, 'is_active' => false, 'limit' => null];
    expect(Validator::make($normalized, PlanCompositionService::rules())->fails())->toBeTrue();
    $normalized['features'] = [['uuid' => $uuid, 'is_active' => true, 'limit' => null, 'plan_id' => 1]];
    expect(Validator::make($normalized, PlanCompositionService::rules())->fails())->toBeTrue();
});
