<?php

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\User;
use App\Services\Central\Plans\PlanActivationService;
use App\Services\Central\Subscriptions\DefaultSubscription;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
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

test('default plan selection grants an existing priced offer for free only after current rights expire', function (): void {
    $actor = planActivationActor(['saas.plans.view', 'saas.plans.activate', 'saas.plans.archive']);
    $this->seed(FreePlanSeeder::class);
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '3000.00', 'annual_price' => '36000.00']);
    (new PlanFeature)->forceFill(['plan_id' => $plan->id,
        'feature_id' => Feature::where('code', 'shops')->sole()->id,
        'limit' => 3, 'is_active' => true])->save();
    $owner = User::factory()->create();
    $paid = Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id,
        'agreed_amount' => '3000.00', 'status' => StatusEnum::ACTIVE,
        'period_starts_at' => now()->subDays(29), 'period_ends_at' => now()->addDay()]);
    $data = ['expected_state' => planActivationState($plan), 'confirmed' => 1];
    $this->actingAs($actor, 'central')->patch(route('central.plan-pages.default', $plan), $data)
        ->assertRedirect()->assertSessionHasNoErrors();
    $defaults = app(DefaultSubscription::class);
    expect($plan->refresh()->is_default)->toBeTrue()->and($plan->monthly_price)->toBe('3000.00')
        ->and($defaults->ensure($owner)->id)->toBe($paid->id);
    $this->travel(2)->days();
    $fallback = $defaults->ensure($owner);
    expect($fallback->plan_id)->toBe($plan->id)->and($fallback->agreed_amount)->toBe('0.00')
        ->and($fallback->period_ends_at)->toBeNull()
        ->and($paid->refresh()->status)->toBe(StatusEnum::EXPIRED)
        ->and($defaults->ensure($owner)->id)->toBe($fallback->id)
        ->and(SubscriptionInstallment::where('subscription_id', $fallback->id)->exists())->toBeFalse()
        ->and(Gate::forUser($actor)->allows('activate', $plan))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('archive', $plan))->toBeFalse();
});

test('default plan selection checks permission confirmation composition and SQL uniqueness', function (): void {
    $actor = planActivationActor(['saas.plans.view', 'saas.plans.activate']);
    $this->seed(FreePlanSeeder::class);
    $free = Plan::where('code', 'free')->sole();
    $url = route('central.plan-pages.default', $free);
    $data = ['expected_state' => planActivationState($free), 'confirmed' => 1];
    $this->actingAs(planActivationActor(['saas.plans.view']), 'central')->patch($url, $data)->assertForbidden();
    $this->actingAs($actor, 'central')->patch($url, ['expected_state' => $data['expected_state']])->assertSessionHasErrors('confirmed');
    $this->patch($url, [...$data, 'expected_state' => str_repeat('0', 64)])->assertSessionHasErrors('expected_state');
    $this->patch($url, $data)->assertRedirect()->assertSessionHasNoErrors();
    $invalid = Plan::factory()->create(['is_active' => true]);
    $this->patch(route('central.plan-pages.default', $invalid), ['expected_state' => planActivationState($invalid), 'confirmed' => 1])->assertSessionHasErrors('plan');
    expect($free->refresh()->is_default)->toBeTrue()->and($invalid->refresh()->is_default)->toBeNull();
    expect(fn () => $invalid->forceFill(['is_default' => true])->save())->toThrow(UniqueConstraintViolationException::class);
});
