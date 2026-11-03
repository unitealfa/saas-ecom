<?php

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Central\Users\JourneyState;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Auth\RegistrationJourney;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Central\Tenants\TenantProfileProjection;
use App\Services\Central\Tenants\TenantStatusService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Events\TenantCreated;

function subscriptionTransitionActor(string $permission): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('subscription-transition-'.Str::random(10), [$permission => 7]));

    return $actor;
}

test('a paid subscription is closed only at the end of its current period', function (): void {
    $actor = subscriptionTransitionActor('saas.subscriptions.close');
    $subscription = Subscription::factory()->create(['period_ends_at' => now()->addMonth(), 'auto_renew' => true]);
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.close', $subscription), [
        'expected_state' => $subscription->transitionState(),
    ])->assertSessionHasNoErrors();
    expect($subscription->fresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and($subscription->fresh()->auto_renew)->toBeFalse()
        ->and($subscription->fresh()->ended_at->equalTo($subscription->period_ends_at))->toBeTrue();
});

test('plan changes are scheduled at the end of the paid period', function (): void {
    $actor = subscriptionTransitionActor('saas.subscriptions.change');
    $subscription = Subscription::factory()->create(['period_ends_at' => now()->addMonth()]);
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '500.00']);
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.change', $subscription), [
        'expected_state' => $subscription->transitionState(), 'plan_uuid' => $plan->uuid,
        'period' => 1, 'expected_amount' => '500.00', 'operation_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();
    $scheduled = Subscription::where('user_id', $subscription->user_id)->where('status', StatusEnum::SCHEDULED)->firstOrFail();
    expect($subscription->fresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and($scheduled->period_starts_at->equalTo($subscription->period_ends_at))->toBeTrue()
        ->and($scheduled->plan_id)->toBe($plan->id);
});

test('repeating the same plan change does not create a second scheduled subscription', function (): void {
    $actor = subscriptionTransitionActor('saas.subscriptions.change');
    $subscription = Subscription::factory()->create(['period_ends_at' => now()->addMonth()]);
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '500.00']);
    $input = ['expected_state' => $subscription->transitionState(), 'plan_uuid' => $plan->uuid,
        'period' => 1, 'expected_amount' => '500.00', 'operation_key' => (string) Str::uuid()];
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.change', $subscription), $input)->assertSessionHasNoErrors();
    $this->post(route('central.subscriptions.change', $subscription), $input)->assertSessionHasNoErrors();
    expect(Subscription::where('user_id', $subscription->user_id)->where('status', StatusEnum::SCHEDULED)->count())->toBe(1);
});

test('a second plan change is refused while another one is scheduled', function (): void {
    $actor = subscriptionTransitionActor('saas.subscriptions.change');
    $subscription = Subscription::factory()->create(['period_ends_at' => now()->addMonth()]);
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '500.00']);
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.change', $subscription), [
        'expected_state' => $subscription->transitionState(), 'plan_uuid' => $plan->uuid,
        'period' => 1, 'expected_amount' => '500.00', 'operation_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();
    $this->post(route('central.subscriptions.change', $subscription), [
        'expected_state' => $subscription->fresh()->transitionState(), 'plan_uuid' => $plan->uuid,
        'period' => 1, 'expected_amount' => '500.00', 'operation_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors('subscription');
    expect(Subscription::where('user_id', $subscription->user_id)->where('status', StatusEnum::SCHEDULED)->count())->toBe(1);
});

test('the scheduled offer becomes current when the previous period ends', function (): void {
    $owner = User::factory()->create();
    $ending = Subscription::factory()->create(['user_id' => $owner->id, 'period_ends_at' => now()->addDay()]);
    $scheduled = Subscription::factory()->create([
        'user_id' => $owner->id, 'status' => StatusEnum::SCHEDULED,
        'started_at' => now()->addDay(), 'period_starts_at' => now()->addDay(), 'period_ends_at' => now()->addMonth(),
    ]);
    $this->travel(2)->days();
    $current = app(DefaultSubscription::class)->ensure($owner);
    expect($ending->fresh()->status)->toBe(StatusEnum::EXPIRED)
        ->and($current->id)->toBe($scheduled->id)
        ->and($current->status)->toBe(StatusEnum::ACTIVE);
});

test('the free offer is restored when a closed paid period ends', function (): void {
    $owner = User::factory()->create();
    $ending = Subscription::factory()->create(['user_id' => $owner->id, 'period_ends_at' => now()->addDay(), 'ended_at' => now()->addDay(), 'auto_renew' => false]);
    $this->travel(2)->days();
    $current = app(DefaultSubscription::class)->ensure($owner);
    expect($ending->fresh()->status)->toBe(StatusEnum::EXPIRED)
        ->and($current->plan->code)->toBe('free')
        ->and($current->status)->toBe(StatusEnum::ACTIVE);
});

test('a permitted closure rejects obsolete form state and a different permission', function (): void {
    $actor = subscriptionTransitionActor('saas.subscriptions.close');
    $subscription = Subscription::factory()->create(['period_ends_at' => now()->addMonth()]);
    $state = $subscription->transitionState();
    $subscription->update(['auto_renew' => true]);
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.close', $subscription), ['expected_state' => $state])
        ->assertSessionHasErrors('expected_state');
    $reader = subscriptionTransitionActor('saas.subscriptions.view');
    $this->actingAs($reader, 'central')->post(route('central.subscriptions.close', $subscription), ['expected_state' => $subscription->fresh()->transitionState()])
        ->assertForbidden();
});

test('a due change applies the new quota while preserving suspended shops', function (): void {
    Event::fake([TenantCreated::class]);
    $reviewer = User::factory()->create();
    $owner = User::factory()->create(['legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now(), 'legal_verified_by_id' => $reviewer->id]);
    $current = Subscription::factory()->create(['user_id' => $owner->id, 'period_ends_at' => now()->addDay()]);
    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create(['code' => 'shops', 'is_active' => true]);
    PlanFeature::factory()->create(['plan_id' => $plan->id, 'feature_id' => $feature->id, 'is_active' => true, 'limit' => 1]);
    $scheduled = Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'status' => StatusEnum::SCHEDULED,
        'started_at' => now()->addDay(), 'period_starts_at' => now()->addDay(), 'period_ends_at' => now()->addMonth()]);
    $first = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE, 'schema_version' => 'ready', 'provisioned_at' => now(), 'activation_priority' => 1]);
    $second = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE, 'schema_version' => 'ready', 'provisioned_at' => now(), 'activation_priority' => 2]);
    $suspended = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::SUSPENDED, 'schema_version' => 'ready', 'provisioned_at' => now()]);
    $this->mock(TenantProfileProjection::class)->shouldReceive('assertPrepared')->once();
    $this->travel(2)->days();
    app(TenantStatusService::class)->synchronizeSubscription($owner);
    expect($current->fresh()->status)->toBe(StatusEnum::EXPIRED)
        ->and($scheduled->fresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and($first->fresh()->status)->toBe(TenantStatus::ACTIVE)
        ->and($second->fresh()->status)->toBe(TenantStatus::OVER_QUOTA)
        ->and($suspended->fresh()->status)->toBe(TenantStatus::SUSPENDED);
});

test('resolving an effective subscription directly also reconciles its shop quota', function (): void {
    Event::fake([TenantCreated::class]);
    $owner = User::factory()->create([
        'legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => User::factory()->create()->id,
    ]);
    Subscription::factory()->create(['user_id' => $owner->id, 'period_ends_at' => now()->addDay()]);
    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create(['code' => 'shops', 'is_active' => true]);
    PlanFeature::factory()->create(['plan_id' => $plan->id, 'feature_id' => $feature->id, 'is_active' => true, 'limit' => 1]);
    Subscription::factory()->create([
        'user_id' => $owner->id, 'plan_id' => $plan->id, 'status' => StatusEnum::SCHEDULED,
        'started_at' => now()->addDay(), 'period_starts_at' => now()->addDay(), 'period_ends_at' => now()->addMonth(),
    ]);
    $first = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE,
        'schema_version' => 'ready', 'provisioned_at' => now(), 'activation_priority' => 1]);
    $second = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE,
        'schema_version' => 'ready', 'provisioned_at' => now(), 'activation_priority' => 2]);
    $this->mock(TenantProfileProjection::class)->shouldReceive('assertPrepared')->once()->withArgs(fn ($shop) => $shop->id === $first->id);
    $this->travel(2)->days();

    app(DefaultSubscription::class)->ensure($owner);
    app(TenantStatusService::class)->synchronizeSubscription($owner);

    expect($first->fresh()->status)->toBe(TenantStatus::ACTIVE);
    expect($second->fresh()->status)->toBe(TenantStatus::OVER_QUOTA);
    Event::assertDispatchedTimes(TenantCreated::class, 2);
});

test('a trial expires at the first applicable deadline', function (?int $periodDays, int $trialDays): void {
    $this->freezeTime();
    $owner = User::factory()->create();
    $trialEnd = now()->addDays($trialDays)->startOfSecond();
    $expectedEnd = now()->addDay()->startOfSecond();
    $trial = Subscription::factory()->create([
        'user_id' => $owner->id, 'status' => StatusEnum::TRIAL,
        'trial_ends_at' => $trialEnd, 'period_ends_at' => $periodDays === null ? null : now()->addDays($periodDays)->startOfSecond(),
    ]);
    $this->travel(1)->days();

    app(TenantStatusService::class)->synchronizeSubscription($owner);

    expect($trial->fresh()->status)->toBe(StatusEnum::EXPIRED);
    expect($trial->fresh()->ended_at->equalTo($expectedEnd))->toBeTrue();
    expect(Subscription::where('user_id', $owner->id)->where('status', StatusEnum::ACTIVE)->sole()->plan->code)->toBe('free');
})->with([
    'trial before billing period' => [30, 1],
    'trial without billing end' => [null, 1],
    'billing period before trial' => [1, 30],
]);

test('an unavailable shop does not break the merchant journey when a subscription expires', function (): void {
    Event::fake([TenantCreated::class]);
    $owner = User::factory()->create([
        'legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => User::factory()->create()->id,
    ]);
    Subscription::factory()->create(['user_id' => $owner->id, 'period_ends_at' => now()->addDay()]);
    $shop = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE,
        'schema_version' => 'ready', 'provisioned_at' => now()]);
    $this->travel(2)->days();

    expect(app(RegistrationJourney::class)->state($owner))->toBe(JourneyState::PREPARING);

    expect($shop->fresh()->status)->toBe(TenantStatus::INACTIVE);
    Event::assertDispatchedTimes(TenantCreated::class, 1);
});

test('a quota reconciliation failure rolls back the subscription transition and its audit', function (): void {
    $owner = User::factory()->create();
    $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'period_ends_at' => now()->addDay()]);
    $before = Activity::count();
    Event::listen(DefaultSubscription::EFFECTIVE_CHANGED, fn () => throw new RuntimeException('Quota reconciliation failed'));
    $this->travel(2)->days();

    expect(fn () => app(DefaultSubscription::class)->ensure($owner))->toThrow(RuntimeException::class, 'Quota reconciliation failed');

    expect($subscription->fresh()->status)->toBe(StatusEnum::ACTIVE);
    expect(Subscription::where('user_id', $owner->id)->count())->toBe(1);
    expect(Activity::count())->toBe($before);
});

test('an unavailable priority shop does not consume a place belonging to a prepared shop', function (): void {
    Event::fake([TenantCreated::class]);
    $owner = User::factory()->create([
        'legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => User::factory()->create()->id,
    ]);
    Subscription::factory()->create(['user_id' => $owner->id, 'period_ends_at' => now()->addDay()]);
    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create(['code' => 'shops', 'is_active' => true]);
    PlanFeature::factory()->create(['plan_id' => $plan->id, 'feature_id' => $feature->id, 'is_active' => true, 'limit' => 1]);
    Subscription::factory()->create([
        'user_id' => $owner->id, 'plan_id' => $plan->id, 'status' => StatusEnum::SCHEDULED,
        'started_at' => now()->addDay(), 'period_starts_at' => now()->addDay(), 'period_ends_at' => now()->addMonth(),
    ]);
    $unavailable = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE,
        'schema_version' => 'ready', 'provisioned_at' => now(), 'activation_priority' => 1]);
    $prepared = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::OVER_QUOTA,
        'schema_version' => 'ready', 'provisioned_at' => now(), 'activation_priority' => 2]);
    $projection = $this->mock(TenantProfileProjection::class);
    $projection->shouldReceive('assertPrepared')->once()->withArgs(fn ($shop) => $shop->id === $unavailable->id)
        ->andThrow(ValidationException::withMessages(['tenant' => 'Base non préparée.']));
    $projection->shouldReceive('assertPrepared')->once()->withArgs(fn ($shop) => $shop->id === $prepared->id);
    $this->travel(2)->days();

    app(DefaultSubscription::class)->ensure($owner);

    expect($unavailable->fresh()->status)->toBe(TenantStatus::INACTIVE);
    expect($prepared->fresh()->status)->toBe(TenantStatus::ACTIVE);
    Event::assertDispatchedTimes(TenantCreated::class, 2);
});
