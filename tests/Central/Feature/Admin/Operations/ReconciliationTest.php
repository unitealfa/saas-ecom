<?php

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum as SubscriptionStatus;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Tenants\TenantProfileProjection;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AuthorizationSeeder::class);
});

function reconciliationActor(array $permissions): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('reconcile-'.Str::random(10), array_fill_keys($permissions, 7)));

    return $actor;
}

test('reconciliation actions have independent permissions', function (): void {
    $subscriber = reconciliationActor(['saas.operations.subscriptions.reconcile']);
    $financial = reconciliationActor(['saas.operations.payments.reconcile']);
    $viewer = reconciliationActor(['saas.operations.view']);
    $this->actingAs($viewer, 'central')->post(route('central.operations.subscriptions.reconcile'))->assertForbidden();
    $this->post(route('central.operations.payments.reconcile'))->assertForbidden();
    $this->actingAs($subscriber, 'central')->post(route('central.operations.payments.reconcile'))->assertForbidden();
    $this->actingAs($financial, 'central')->post(route('central.operations.subscriptions.reconcile'))->assertForbidden();
    $this->actingAs(User::factory()->create(), 'central')->post(route('central.operations.subscriptions.reconcile'))->assertForbidden();
    $this->actingAs($subscriber, 'central')->get(route('central.operations.index'))->assertOk()
        ->assertSee('Réconcilier les abonnements')->assertDontSee('Rapprocher les paiements');
});

test('subscription reconciliation applies expired transitions and shop quotas idempotently', function (): void {
    Event::fake([TenantCreated::class]);
    $this->seed(FreePlanSeeder::class);
    $actor = reconciliationActor(['saas.operations.subscriptions.reconcile']);
    $owner = User::factory()->create(['legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_profile_version' => 1, 'legal_verified_at' => now(), 'legal_verified_by_id' => $actor->id]);
    $subscription = Subscription::factory()->create(['user_id' => $owner->id,
        'status' => SubscriptionStatus::ACTIVE, 'started_at' => now()->subMonth(),
        'period_starts_at' => now()->subMonth(), 'period_ends_at' => now()->subDay()]);
    $staffSubscription = Subscription::factory()->create(['user_id' => $actor->id,
        'status' => SubscriptionStatus::ACTIVE, 'started_at' => now()->subMonth(),
        'period_starts_at' => now()->subMonth(), 'period_ends_at' => now()->subDay()]);
    Tenant::factory()->count(3)->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE,
        'schema_version' => 'ready', 'provisioned_at' => now()]);
    $this->mock(TenantProfileProjection::class)->shouldReceive('assertPrepared')->twice();

    $this->actingAs($actor, 'central')->post(route('central.operations.subscriptions.reconcile'))
        ->assertSessionHasNoErrors()->assertRedirect(route('central.operations.index'))
        ->assertSessionHas('operations_result', fn (array $result): bool => $result['processed'] === 1 && $result['failed'] === 0);
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::EXPIRED)
        ->and($staffSubscription->refresh()->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and(Subscription::where('user_id', $owner->id)->where('status', SubscriptionStatus::ACTIVE)->count())->toBe(1)
        ->and(Tenant::where('user_id', $owner->id)->where('status', TenantStatus::OVER_QUOTA)->count())->toBe(1)
        ->and(Activity::forSubject($owner)->where('event', 'operations_subscription_reconciled')->count())->toBe(1);
    $this->post(route('central.operations.subscriptions.reconcile'))->assertSessionHasNoErrors();
    expect(Subscription::where('user_id', $owner->id)->count())->toBe(2);
});

test('central financial reconciliation reports uncertain payments without changing them', function (): void {
    $fixture = manualPaymentFixture(issued: false);
    $payment = new SaasPayment;
    $payment->forceFill(['user_id' => $fixture['owner']->id, 'document_id' => $fixture['invoice']->id,
        'record_type' => 1, 'transfer_method' => 4, 'transfer_status' => TransferStatusEnum::UNCERTAIN,
        'amount' => '1500.00', 'currency' => 'DZD', 'created_by_id' => $fixture['actor']->id,
        'operation_key' => 'saas:payment:test:'.Str::uuid(), 'correlation_id' => Str::uuid()])->save();
    $actor = reconciliationActor(['saas.operations.payments.reconcile']);
    $before = $payment->fresh()->getAttributes();

    $this->actingAs($actor, 'central')->post(route('central.operations.payments.reconcile'))
        ->assertSessionHasNoErrors()->assertRedirect(route('central.operations.index'))
        ->assertSessionHas('operations_result', fn (array $result): bool => $result['processed'] === 1
            && count($result['anomalies']) === 1 && $result['anomalies'][0]['uuid'] === $payment->uuid);
    $this->get(route('central.operations.index'))->assertOk()->assertSee($payment->uuid)->assertSee('Paiement incertain à examiner');
    expect($payment->refresh()->getAttributes())->toBe($before);
    expect(Activity::where('event', 'operations_payments_reconciled')->count())->toBe(1);
});

test('a failed subscription transition is rolled back and reported for review', function (): void {
    $actor = reconciliationActor(['saas.operations.subscriptions.reconcile']);
    $owner = User::factory()->create();
    $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'status' => SubscriptionStatus::ACTIVE,
        'started_at' => now()->subMonth(), 'period_starts_at' => now()->subMonth(), 'period_ends_at' => now()->subDay()]);

    $this->actingAs($actor, 'central')->post(route('central.operations.subscriptions.reconcile'))
        ->assertSessionHas('operations_result', fn (array $result): bool => $result['processed'] === 0
            && $result['failed'] === 1 && $result['failed_owners'] === [$owner->uuid]);
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and(Activity::forSubject($owner)->where('event', 'operations_subscription_reconciliation_failed')->count())->toBe(1);
});

test('financial report is hidden from an account without its reconciliation permission', function (): void {
    $viewer = reconciliationActor(['saas.operations.view']);
    $this->actingAs($viewer, 'central')->withSession(['operations_result' => [
        'type' => 'payments', 'processed' => 1, 'anomalies' => [['uuid' => 'private-payment-reference', 'reasons' => ['Test']]],
        'next_cursor' => null,
    ]])->get(route('central.operations.index'))->assertOk()->assertDontSee('private-payment-reference');
});

test('financial reconciliation continues through bounded batches', function (): void {
    $fixture = manualPaymentFixture(issued: false);
    for ($index = 0; $index < 51; $index++) {
        (new SaasPayment)->forceFill(['user_id' => $fixture['owner']->id, 'document_id' => $fixture['invoice']->id,
            'record_type' => 1, 'transfer_method' => 4, 'transfer_status' => TransferStatusEnum::UNCERTAIN,
            'amount' => '1.00', 'currency' => 'DZD', 'created_by_id' => $fixture['actor']->id,
            'operation_key' => 'saas:payment:test:'.Str::uuid(), 'correlation_id' => Str::uuid()])->save();
    }
    $actor = reconciliationActor(['saas.operations.payments.reconcile']);
    $this->actingAs($actor, 'central')->post(route('central.operations.payments.reconcile'))
        ->assertSessionHas('operations_result', fn (array $result): bool => $result['processed'] === 50 && $result['next_cursor'] !== null);
    $cursor = session('operations_result.next_cursor');
    $this->post(route('central.operations.payments.reconcile'), ['cursor' => $cursor])
        ->assertSessionHas('operations_result', fn (array $result): bool => $result['processed'] === 1 && $result['next_cursor'] === null);
    $this->post(route('central.operations.payments.reconcile'), ['cursor' => 'invalid'])->assertSessionHasErrors('cursor');
});
