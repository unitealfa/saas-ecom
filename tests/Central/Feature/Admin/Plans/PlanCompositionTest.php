<?php

use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\User;
use App\Services\Central\Plans\PlanCompositionService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

function planCompositionActor(array $permissions): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('plan-composition-'.Str::random(10), array_fill_keys($permissions, 7)));

    return $actor;
}

function planCompositionState(Plan $plan): string
{
    return $plan->compositionState($plan->features()->orderBy('feature_id')->get());
}

test('a permitted actor replaces a plan composition without replacing preserved association identities', function (): void {
    $actor = planCompositionActor(['saas.plans.view', 'saas.plans.features.update']);
    $plan = Plan::factory()->create(['is_active' => false]);
    $oldFeature = Feature::factory()->create();
    $old = PlanFeature::factory()->create(['plan_id' => $plan->id, 'feature_id' => $oldFeature->id, 'limit' => 2]);
    $boolean = Feature::factory()->create(['value_type' => ValueTypeEnum::BOOLEAN]);
    $state = planCompositionState($plan);

    $this->actingAs($actor, 'central')->getJson(route('central.plans.show', $plan->uuid))
        ->assertOk()->assertJsonPath('data.expected_state', $state);
    $this->putJson(route('central.plans.features.update', $plan->uuid), [
        'expected_state' => $state,
        'features' => [
            ['uuid' => $oldFeature->uuid, 'is_active' => true, 'limit' => 8],
            ['uuid' => $boolean->uuid, 'is_active' => true, 'limit' => null],
        ],
    ])->assertNoContent();

    expect($old->refresh()->limit)->toBe(8)->and($plan->features()->count())->toBe(2);
    expect(Activity::forSubject($plan)->where('event', 'plan_features_updated')->count())->toBe(1);
    $this->putJson(route('central.plans.features.update', $plan->uuid), [
        'expected_state' => $state, 'features' => [],
    ])->assertUnprocessable();
    expect($plan->features()->count())->toBe(2);
});

test('composition requires its dedicated permission and rejects invalid feature data', function (): void {
    $actor = planCompositionActor(['saas.plans.view']);
    $plan = Plan::factory()->create();
    $this->actingAs($actor, 'central')->putJson(route('central.plans.features.update', $plan->uuid), [
        'expected_state' => planCompositionState($plan), 'features' => [],
    ])->assertForbidden();
    expect(fn () => app(PlanCompositionService::class)->update($actor, $plan, [
        'expected_state' => planCompositionState($plan), 'features' => [],
    ]))->toThrow(AuthorizationException::class);
});

test('an empty composition removes existing grants from an unused plan', function (): void {
    $actor = planCompositionActor(['saas.plans.features.update']);
    $plan = Plan::factory()->create();
    PlanFeature::factory()->create(['plan_id' => $plan->id]);

    $this->actingAs($actor, 'central')->putJson(route('central.plans.features.update', $plan->uuid), [
        'expected_state' => planCompositionState($plan), 'features' => [],
    ])->assertNoContent();
    expect($plan->features()->count())->toBe(0);
    expect(Activity::forSubject($plan)->where('event', 'plan_features_updated')->count())->toBe(1);
});

test('composition rejects unavailable or duplicate features and numeric limits on boolean features', function (): void {
    $actor = planCompositionActor(['saas.plans.features.update']);
    $plan = Plan::factory()->create();
    $inactive = Feature::factory()->create(['is_active' => false]);
    $boolean = Feature::factory()->create(['value_type' => ValueTypeEnum::BOOLEAN]);
    $state = planCompositionState($plan);
    $this->actingAs($actor, 'central');
    foreach ([
        [['uuid' => $inactive->uuid, 'is_active' => true, 'limit' => 1]],
        [['uuid' => $boolean->uuid, 'is_active' => true, 'limit' => 1]],
        [['uuid' => $boolean->uuid, 'is_active' => true, 'limit' => null], ['uuid' => $boolean->uuid, 'is_active' => true, 'limit' => null]],
    ] as $features) {
        $this->putJson(route('central.plans.features.update', $plan->uuid), ['expected_state' => $state, 'features' => $features])
            ->assertUnprocessable();
    }
    expect($plan->features()->count())->toBe(0);
});

test('used and protected free plan versions cannot have their composition changed', function (): void {
    $actor = planCompositionActor(['saas.plans.features.update']);
    $owner = User::factory()->create();
    $used = Plan::factory()->create();
    Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $used->id]);
    $free = Plan::factory()->create(['code' => 'free']);
    $this->actingAs($actor, 'central')->putJson(route('central.plans.features.update', $used->uuid), [
        'expected_state' => planCompositionState($used), 'features' => [],
    ])->assertUnprocessable();
    $this->putJson(route('central.plans.features.update', $free->uuid), [
        'expected_state' => planCompositionState($free), 'features' => [],
    ])->assertForbidden();
});
