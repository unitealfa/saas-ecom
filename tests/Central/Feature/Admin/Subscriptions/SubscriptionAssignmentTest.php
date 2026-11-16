<?php

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Billing\SaasPayment;
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

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

function assignmentInput(User $owner, Plan $plan): array
{
    return ['owner_uuid' => $owner->uuid, 'plan_uuid' => $plan->uuid, 'period' => 1,
        'operation_key' => (string) Str::uuid(), 'expected_amount' => $plan->monthly_price,
        'payment_choice' => 1, 'received' => true, 'reference' => 'TEST-'.Str::uuid(), 'occurred_at' => now()->toDateTimeString()];
}

test('assignment confirms payment and replaces free rights only after documents then remains idempotent', function (): void {
    $f = manualPaymentFixture(issued: false);
    $f['subscription']->forceFill(['status' => StatusEnum::CANCELLED])->save();
    $actor = subscriptionAssignmentActor('saas.subscriptions.assign');
    $actor->givePermissionTo('saas.payments.validate');
    $owner = $f['owner'];
    $free = Plan::factory()->create(['code' => 'free']);
    $previous = Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $free->id]);
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '500.00']);
    $input = assignmentInput($owner, $plan);
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.store'), $input)->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('central.subscriptions.store'), $input)->assertSessionHasNoErrors()->assertRedirect();
    $assigned = Subscription::where('user_id', $owner->id)->where('plan_id', $plan->id)->sole();
    expect($previous->fresh()->status)->toBe(StatusEnum::CANCELLED)
        ->and($assigned->status)->toBe(StatusEnum::ACTIVE)
        ->and(Subscription::where('user_id', $owner->id)->where('status', StatusEnum::ACTIVE)->count())->toBe(1)
        ->and(SubscriptionInstallment::where('subscription_id', $assigned->id)->sole()->installment_status)->toBe(InstallmentStatusEnum::PAID)
        ->and($assigned->request->invoice->status)->toBe(DocumentStatusEnum::ISSUED)
        ->and(SaasPayment::count())->toBe(1);
});

test('assignment cannot replace an active paid subscription without the change permission', function (): void {
    $f = manualPaymentFixture(issued: false);
    $f['subscription']->forceFill(['status' => StatusEnum::ACTIVE])->save();
    $actor = subscriptionAssignmentActor('saas.subscriptions.assign');
    $actor->givePermissionTo('saas.payments.validate');
    $plan = Plan::factory()->create(['is_active' => true]);
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.store'), assignmentInput($f['owner'], $plan))->assertForbidden();
    expect($f['subscription']->fresh()->status)->toBe(StatusEnum::ACTIVE);
});

test('the price displayed in the form must still match the offer', function (): void {
    $f = manualPaymentFixture(issued: false);
    $f['subscription']->forceFill(['status' => StatusEnum::CANCELLED])->save();
    $actor = subscriptionAssignmentActor('saas.subscriptions.assign');
    $actor->givePermissionTo('saas.payments.validate');
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '500.00']);
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.store'), [
        ...assignmentInput($f['owner'], $plan), 'quoted_prices' => [$plan->uuid => [1 => '400.00']],
    ])->assertSessionHasErrors('plan_uuid');
    expect(Subscription::where('user_id', $f['owner']->id)->where('plan_id', $plan->id)->count())->toBe(0);
});

test('assignment permission alone never confirms money', function (): void {
    $actor = subscriptionAssignmentActor('saas.subscriptions.assign');
    $this->actingAs($actor, 'central')->post(route('central.subscriptions.store'), [])->assertForbidden();
});
