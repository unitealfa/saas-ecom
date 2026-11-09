<?php

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Str;

function subscriptionAssignmentActor(string $permission): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('subscription-assignment-'.Str::random(10), [$permission => 7]));

    return $actor;
}

test('an actor without assignment permission cannot assign an offer', function (): void {
    $actor = subscriptionAssignmentActor('saas.subscriptions.view');
    $owner = User::factory()->create();
    $plan = Plan::factory()->create(['is_active' => true]);
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.store'), [
        'owner_uuid' => $owner->uuid, 'plan_uuid' => $plan->uuid,
        'period' => 1, 'operation_key' => (string) Str::uuid(),
    ])->assertForbidden();
});

test('assignment preserves the free subscription and is idempotent', function (): void {
    $actor = subscriptionAssignmentActor('saas.subscriptions.assign');
    $owner = User::factory()->create();
    $free = Plan::factory()->create(['code' => 'free']);
    $previous = Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $free->id]);
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '500.00']);
    $input = ['owner_uuid' => $owner->uuid, 'plan_uuid' => $plan->uuid, 'period' => 1, 'operation_key' => (string) Str::uuid(), 'expected_amount' => '500.00'];
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.store'), $input)->assertRedirect();
    $this->post(route('central.subscriptions.store'), $input)->assertRedirect();
    expect($previous->fresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and(Subscription::where('user_id', $owner->id)->where('status', StatusEnum::ACTIVE)->count())->toBe(1)
        ->and(Subscription::where('user_id', $owner->id)->where('plan_id', $plan->id)->sole()->status)->toBe(StatusEnum::SCHEDULED)
        ->and(SubscriptionInstallment::where('subscription_id', Subscription::where('plan_id', $plan->id)->sole()->id)->count())->toBe(1);
});

test('assignment does not replace a paid subscription in progress', function (): void {
    $actor = subscriptionAssignmentActor('saas.subscriptions.assign');
    $owner = User::factory()->create();
    $current = Subscription::factory()->create(['user_id' => $owner->id, 'period_ends_at' => now()->addMonth()]);
    $plan = Plan::factory()->create(['is_active' => true]);
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.store'), [
        'owner_uuid' => $owner->uuid, 'plan_uuid' => $plan->uuid,
        'period' => 1, 'operation_key' => (string) Str::uuid(), 'expected_amount' => $plan->monthly_price,
    ])->assertSessionHasErrors();
    expect($current->fresh()->status)->toBe(StatusEnum::ACTIVE);
});

test('the price displayed in the form must still match the offer', function (): void {
    $actor = subscriptionAssignmentActor('saas.subscriptions.assign');
    $owner = User::factory()->create();
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '500.00']);
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.store'), [
        'owner_uuid' => $owner->uuid, 'plan_uuid' => $plan->uuid, 'period' => 1,
        'operation_key' => (string) Str::uuid(), 'quoted_prices' => [$plan->uuid => [1 => '400.00']],
    ])->assertSessionHasErrors('plan_uuid');
    expect(Subscription::where('user_id', $owner->id)->count())->toBe(0);
});
