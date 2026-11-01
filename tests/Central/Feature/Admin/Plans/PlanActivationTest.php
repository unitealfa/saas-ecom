<?php

use App\Models\Central\Audit\Activity;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\User;
use App\Services\Central\Plans\PlanActivationService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

function planActivationActor(array $permissions): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('plan-activation-'.Str::random(10), array_fill_keys($permissions, 7)));

    return $actor;
}

function planActivationState(Plan $plan): string
{
    return $plan->compositionState($plan->features()->orderBy('feature_id')->get());
}

test('an authorized actor changes commercial availability without changing existing subscriptions', function (): void {
    $actor = planActivationActor(['saas.plans.activate']);
    $owner = User::factory()->create();
    $plan = Plan::factory()->create(['is_active' => false]);
    $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
    $oldState = planActivationState($plan);

    $this->actingAs($actor, 'central')->patchJson(route('central.plans.activate', $plan->uuid), [
        'expected_state' => $oldState, 'is_active' => true,
    ])->assertNoContent();
    expect($plan->refresh()->is_active)->toBeTrue()
        ->and($subscription->refresh()->plan_id)->toBe($plan->id);
    expect(Activity::forSubject($plan)->where('event', 'plan_activation_updated')->count())->toBe(1);

    $this->patchJson(route('central.plans.activate', $plan->uuid), [
        'expected_state' => $oldState, 'is_active' => false,
    ])->assertUnprocessable();
    expect($plan->refresh()->is_active)->toBeTrue();

    $this->patchJson(route('central.plans.activate', $plan->uuid), [
        'expected_state' => planActivationState($plan), 'is_active' => false,
    ])->assertNoContent();
    expect($plan->refresh()->is_active)->toBeFalse()
        ->and($subscription->refresh()->plan_id)->toBe($plan->id);
});

test('commercial activation requires its own permission including direct service calls', function (): void {
    $actor = planActivationActor(['saas.plans.view']);
    $plan = Plan::factory()->create(['is_active' => false]);
    $payload = ['expected_state' => planActivationState($plan), 'is_active' => true];

    $this->actingAs($actor, 'central')->patchJson(route('central.plans.activate', $plan->uuid), $payload)->assertForbidden();
    expect(fn () => app(PlanActivationService::class)->setActive($actor, $plan, $payload))->toThrow(AuthorizationException::class);
    expect($plan->refresh()->is_active)->toBeFalse();
});

test('the system free plan cannot be activated or deactivated through administration', function (): void {
    $actor = planActivationActor(['saas.plans.activate']);
    $plan = Plan::factory()->create(['code' => 'free', 'is_active' => true]);

    $this->actingAs($actor, 'central')->patchJson(route('central.plans.activate', $plan->uuid), [
        'expected_state' => planActivationState($plan), 'is_active' => false,
    ])->assertForbidden();
    expect($plan->refresh()->is_active)->toBeTrue();
});

test('activating a version atomically stops selling other versions while preserving subscriptions', function (): void {
    $actor = planActivationActor(['saas.plans.activate']);
    $owner = User::factory()->create();
    $old = Plan::factory()->create(['code' => 'pro', 'version' => 1, 'is_active' => true]);
    $new = Plan::factory()->create(['code' => 'pro', 'version' => 2, 'is_active' => false]);
    $other = Plan::factory()->create(['code' => 'basic', 'is_active' => true]);
    $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $old->id]);

    $this->actingAs($actor, 'central')->patchJson(route('central.plans.activate', $new->uuid), [
        'expected_state' => planActivationState($new), 'is_active' => true,
    ])->assertNoContent();

    expect($new->refresh()->is_active)->toBeTrue()->and($old->refresh()->is_active)->toBeFalse()
        ->and($other->refresh()->is_active)->toBeTrue()->and($subscription->refresh()->plan_id)->toBe($old->id);
    expect(Activity::forSubject($old)->where('event', 'plan_activation_updated')->count())->toBe(1)
        ->and(Activity::forSubject($new)->where('event', 'plan_activation_updated')->count())->toBe(1);
});

test('activating an already active version resolves other active versions without a duplicate audit', function (): void {
    $actor = planActivationActor(['saas.plans.activate']);
    $first = Plan::factory()->create(['code' => 'pro', 'version' => 1, 'is_active' => true]);
    $second = Plan::factory()->create(['code' => 'pro', 'version' => 2, 'is_active' => true]);

    $this->actingAs($actor, 'central')->patchJson(route('central.plans.activate', $second->uuid), [
        'expected_state' => planActivationState($second), 'is_active' => true,
    ])->assertNoContent();

    expect($first->refresh()->is_active)->toBeFalse()->and($second->refresh()->is_active)->toBeTrue();
    expect(Activity::forSubject($first)->where('event', 'plan_activation_updated')->count())->toBe(1)
        ->and(Activity::forSubject($second)->where('event', 'plan_activation_updated')->count())->toBe(0);
});
