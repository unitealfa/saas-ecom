<?php

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\VerificationStatusEnum;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Central\Tenants\TenantProfileProjection;
use App\Services\Central\Tenants\TenantStatusService;
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
    $owner = User::factory()->create(['legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now()]);
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
