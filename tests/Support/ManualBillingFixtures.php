<?php

use App\Enums\Central\Billing\TriggerEventEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\User;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasInvoiceService;
use App\Services\Central\Subscriptions\SubscriptionPurchaseService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** @return array<string, mixed> */
function manualPaymentFixture(bool $future = false, bool $purchase = false, array $rates = [], bool $issued = true): array
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));
    $owner = User::factory()->create();
    $owner->forceFill(['legal_address' => 'Adresse fictive de test', 'nif' => '123456789012345',
        'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now()->subDay(),
        'legal_verified_by_id' => $actor->id, 'legal_profile_version' => 1])->save();
    if ($purchase) {
        $plan = Plan::factory()->create(['name' => 'Pro fictif', 'is_active' => true, 'monthly_price' => '3000.00', 'annual_price' => '36000.00']);
        $subscription = app(SubscriptionPurchaseService::class)->choose($owner,
            ['plan_uuid' => $plan->uuid, 'period' => 1, 'operation_id' => (string) Str::uuid(), 'expected_amount' => '3000.00']);
        $due = SubscriptionInstallment::where('subscription_id', $subscription->id)->sole();
    } else {
        $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'agreed_amount' => '3000.00',
            'status' => StatusEnum::SCHEDULED, 'period_starts_at' => $future ? now()->addDays(2) : now()->subDay(),
            'period_ends_at' => $future ? now()->addDays(2)->addMonth() : now()->addMonth()]);
        $due = new SubscriptionInstallment;
        $due->forceFill(['user_id' => $owner->id, 'subscription_id' => $subscription->id,
            'operation_key' => 'installment:manual:'.Str::uuid(), 'installment_number' => 'TEST-'.Str::uuid(),
            'installment_amount' => '3000.00', 'installment_status' => InstallmentStatusEnum::PENDING,
            'due_at' => now()->addDay(), 'period_starts_at' => $subscription->period_starts_at, 'period_ends_at' => $subscription->period_ends_at])->save();
        $due->refresh();
    }
    $rules = app(BillingRuleService::class);
    $rule = $rules->create($actor, ['code' => 'payment.test.'.Str::lower(Str::random(8)), 'trigger_event' => TriggerEventEnum::INSTALLMENT_DUE->value,
        'parameters' => ['document_type' => 1, 'payment_due_days' => 10]], (string) Str::uuid());
    $rules->validateRule($actor, $rule, $rule->managementState(), 'TEST-RULE');
    $rule->refresh();
    $rules->activate($actor, $rule, $rule->managementState(), now()->subDay()->toDateTimeString(), null);
    config(['central.billing.issuer' => ['name' => 'Émetteur fictif', 'address' => 'Adresse fictive', 'nif' => '999999999999999',
        'validation_reference' => 'TEST-ISSUER', 'validated_at' => now()->subDay()->toISOString()],
        'central.billing.customers' => [$owner->uuid => ['name' => trim($owner->first_name.' '.$owner->last_name),
            'address' => $owner->legal_address, 'nif' => $owner->getAttribute('nif'),
            'validation_reference' => 'TEST-CUSTOMER', 'validated_at' => now()->subDay()->toISOString()]],
        'central.billing.tax_policy' => ['validation_reference' => 'TEST-TAX', 'validated_at' => now()->subDay()->toISOString(),
            'rates' => $rates, 'no_tax_reason' => $rates === [] ? 'Fixture explicitement sans taxe' : null]]);
    $disk = 'payment-test-'.getmypid();
    if (! str_contains((string) config('filesystems.disks.local.root'), $disk)) {
        Storage::fake($disk);
        config(['filesystems.disks.local' => ['driver' => 'local', 'root' => Storage::disk($disk)->path(''), 'throw' => true]]);
        Storage::forgetDisk('local');
    }
    $draft = app(SaasInvoiceService::class)->prepareInstallment($actor, $subscription, $due, $rule->uuid, $due->managementState());
    $invoice = $issued ? app(SaasInvoiceIssuanceService::class)->issue($actor, $draft, $draft->managementState()) : $draft;

    return compact('actor', 'owner', 'subscription', 'due', 'invoice');
}

/** @return array<string, mixed> */
function manualExternalPayload(string $amount = '3000.00'): array
{
    return ['amount' => $amount, 'currency' => 'DZD', 'occurred_at' => now()->toDateTimeString(),
        'operation_id' => (string) Str::uuid(), 'method' => 3, 'reference' => 'DECLARED-TEST'];
}

/** @return array<string, mixed> */
function manualVerification(SaasPayment $payment): array
{
    return ['expected_state' => $payment->managementState(), 'received' => true, 'account' => 'TEST-CCP-001',
        'reference' => 'TEST-REFERENCE-001', 'occurred_at' => now()->toDateTimeString()];
}
