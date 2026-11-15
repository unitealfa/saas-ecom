<?php

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

use App\Enums\Central\Billing\DeliveryStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\TransferMethodEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Enums\Central\Subscriptions\PaymentChoiceEnum;
use App\Enums\Central\Subscriptions\RequestStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Jobs\Central\SendSaasDocumentJob;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Models\Central\Plans\Plan;
use App\Models\User;
use App\Notifications\Central\Subscriptions\SubscriptionWorkflowNotification;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\SaasCreditNoteService;
use App\Services\Central\Billing\SaasDocumentDeliveryService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasPaymentService;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Central\Subscriptions\SubscriptionRequestPaymentState;
use App\Services\Central\Subscriptions\SubscriptionRequestService;
use App\Services\Central\Subscriptions\SubscriptionWorkflowNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

function administrationRequestFixture(): array
{
    $f = manualPaymentFixture(issued: false);
    $f['owner'] = App\Models\Central\Users\User::findOrFail($f['owner']->id);
    $f['actor'] = App\Models\Central\Users\User::findOrFail($f['actor']->id);
    $f['subscription']->forceFill(['status' => StatusEnum::CANCELLED])->save();
    $f['plan'] = Plan::factory()->create(['is_active' => true, 'monthly_price' => '3000.00', 'annual_price' => '36000.00']);

    return $f;
}

function administrationRequestInput(array $f, PaymentChoiceEnum $choice = PaymentChoiceEnum::CASH): array
{
    return ['plan_uuid' => $f['plan']->uuid, 'period' => 1, 'expected_amount' => '3000.00',
        'payment_choice' => $choice->value, 'operation_id' => (string) Str::uuid()];
}

test('request lists preserve refused decisions and notify the owner with the required reason', function (): void {
    Notification::fake();
    Queue::fake();
    $f = administrationRequestFixture();
    $service = app(SubscriptionRequestService::class);
    $input = administrationRequestInput($f);
    $request = $service->create($f['owner'], $input);
    $service->create($f['owner'], $input);
    Notification::assertSentToTimes($f['owner'], SubscriptionWorkflowNotification::class, 2);
    Notification::assertSentTo($f['actor'], SubscriptionWorkflowNotification::class, fn ($notice) => $notice->administrative);
    $this->actingAs($f['actor'], 'central')->get(route('central.subscription-requests.index', ['filter' => 'ongoing']))
        ->assertOk()->assertSee($request->uuid)->assertSee($request->subscription->plan->name);
    $this->post(route('central.subscription-requests.reject', $request), ['expected_state' => $request->managementState(), 'reason' => ' '])
        ->assertSessionHasErrors('reason');
    $this->post(route('central.subscription-requests.reject', $request), ['expected_state' => $request->managementState(), 'reason' => 'Justificatif illisible'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $request->refresh();
    expect($request->status)->toBe(RequestStatusEnum::REJECTED)
        ->and($request->decided_by_id)->toBe($f['actor']->id)
        ->and($request->decided_at)->not->toBeNull()
        ->and($request->subscription->status)->toBe(StatusEnum::SCHEDULED);
    Notification::assertSentTo($f['owner'], SubscriptionWorkflowNotification::class,
        fn ($notice) => $notice->event === SubscriptionRequestPaymentState::REJECTED && str_contains($notice->toMail($f['owner'])->introLines[0], 'Justificatif illisible'));
    foreach (['all', 'rejected'] as $filter) {
        $this->get(route('central.subscription-requests.index', ['filter' => $filter]))->assertOk()->assertSee($request->uuid);
    }
    $this->get(route('central.subscription-requests.index', ['filter' => 'ongoing']))->assertOk()->assertDontSee($request->uuid);
    $this->actingAs($f['owner'], 'central')->get(route('owner.subscription-requests.show', $request))
        ->assertOk()->assertSee('Justificatif illisible')->assertSee($f['actor']->name);
    $this->actingAs(User::factory()->create(), 'central')->get(route('owner.subscription-requests.show', $request))->assertForbidden();
    expect(Activity::forSubject($request)->where('event', SubscriptionRequestPaymentState::REJECTED)->count())->toBe(1);
});

test('accepted requests expose historical invoices receipts and payment status without cross-owner downloads', function (): void {
    Notification::fake();
    Queue::fake();
    $f = administrationRequestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], administrationRequestInput($f));
    $request = $service->accept($f['actor'], $request, $request->managementState(), [
        'amount' => '3000.00', 'received' => true, 'operation_id' => (string) Str::uuid(), 'occurred_at' => now()->toDateTimeString(),
    ]);
    $this->actingAs($f['actor'], 'central')->get(route('central.subscription-requests.index', ['filter' => 'accepted']))->assertOk()->assertSee($request->uuid);
    $this->get(route('central.invoices.index', ['filter' => 'paid']))->assertOk()->assertSee($request->invoice->number);
    $this->get(route('central.invoices.index', ['filter' => 'unpaid']))->assertOk()->assertDontSee($request->invoice->number);
    $this->get(route('central.invoices.show', $request->invoice))->assertOk()->assertSee('Statut d’envoi')->assertSee('Télécharger le reçu')->assertDontSee('Facturation — Envois');
    foreach (['central' => $f['actor'], 'owner' => $f['owner']] as $prefix => $actor) {
        $this->actingAs($actor, 'central')->get(URL::temporarySignedRoute($prefix.'.invoices.download', now()->addMinutes(5), ['invoice' => $request->invoice]))->assertOk();
        $this->get(URL::temporarySignedRoute($prefix.'.payments.receipt', now()->addMinutes(5), ['payment' => $request->payment]))->assertOk();
    }
    Notification::assertSentTo($f['owner'], SubscriptionWorkflowNotification::class, fn ($notice) => $notice->event === SubscriptionRequestPaymentState::ACCEPTED);
    Notification::assertSentTo($f['owner'], SubscriptionWorkflowNotification::class, fn ($notice) => $notice->event === SubscriptionWorkflowNotifier::RECEIPT_READY);
    $other = User::factory()->create();
    $this->actingAs($other, 'central')->get(URL::temporarySignedRoute('owner.invoices.download', now()->addMinutes(5), ['invoice' => $request->invoice]))->assertForbidden();
    $this->get(URL::temporarySignedRoute('owner.payments.receipt', now()->addMinutes(5), ['payment' => $request->payment]))->assertForbidden();
});

test('new bank proofs notify both parties and cancelled requests remain available', function (): void {
    Notification::fake();
    Queue::fake();
    $f = administrationRequestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], administrationRequestInput($f, PaymentChoiceEnum::EXTERNAL));
    $proof = UploadedFile::fake()->createWithContent('bank-proof.pdf', Storage::disk('local')->get($request->invoice->media->storage_key));
    $request = $service->submitProof($f['owner'], $request, $request->managementState(), [
        'method' => TransferMethodEnum::BANK_TRANSFER->value, 'amount' => '3000.00', 'currency' => 'DZD',
        'occurred_at' => now()->toDateTimeString(), 'operation_id' => (string) Str::uuid(), 'reference' => 'BANK-TEST',
    ], $proof);
    expect($request->status)->toBe(RequestStatusEnum::REVIEW);
    foreach ([$f['owner'], $f['actor']] as $recipient) {
        Notification::assertSentTo($recipient, SubscriptionWorkflowNotification::class, fn ($notice) => $notice->event === SubscriptionRequestPaymentState::SUBMITTED);
    }
    $this->actingAs($f['actor'], 'central')->get(route('central.subscription-requests.show', $request))->assertOk()->assertSee('Télécharger le justificatif');
    config(['central.billing.issuer' => []]);
    $other = User::factory()->create(['legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => $f['actor']->id, 'legal_profile_version' => 1]);
    $cancelled = $service->createFor($f['actor'], $other, administrationRequestInput($f));
    $this->get(route('central.subscription-requests.index', ['status' => RequestStatusEnum::AWAITING_PAYMENT->value]))
        ->assertOk()->assertSee($cancelled->uuid)->assertDontSee($request->uuid);
    $cancelled = $service->cancel($other, $cancelled, $cancelled->managementState());
    $this->get(route('central.subscription-requests.index', ['filter' => 'cancelled']))->assertOk()->assertSee($cancelled->uuid)->assertDontSee($request->uuid);
});

test('document email stays inside its invoice and explicit resend preserves previous attempts', function (): void {
    Notification::fake();
    Queue::fake();
    Mail::fake();
    $f = manualPaymentFixture();
    config(['mail.default' => 'smtp']);
    $delivery = SaasDocumentDelivery::sole();
    $this->actingAs($f['actor'], 'central')->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])
        ->assertSessionHasNoErrors()->assertRedirect(route('central.invoices.show', $f['invoice']));
    app(SaasDocumentDeliveryService::class)->process($delivery->id);
    $delivery->refresh();
    expect($delivery->delivery_status)->toBe(DeliveryStatusEnum::SENT);
    $attempts = $delivery->delivery_attempts;
    $this->get(route('central.invoices.show', $f['invoice']))->assertOk()->assertSee('Renvoyer le document')->assertSee(DeliveryStatusEnum::SENT->label());
    $this->get('/admin/billing/deliveries/'.$delivery->uuid)->assertNotFound();
    $this->post(route('central.deliveries.resend', $delivery), ['expected_state' => $delivery->managementState()])->assertSessionHasNoErrors()->assertRedirect(route('central.invoices.show', $f['invoice']));
    expect($delivery->refresh()->delivery_attempts)->toBe($attempts);
    app(SaasDocumentDeliveryService::class)->process($delivery->id);
    expect($delivery->refresh()->attempts_count)->toBe(2)
        ->and($delivery->delivery_attempts[0])->toBe($attempts[0]);
    Queue::assertPushed(SendSaasDocumentJob::class, 2);
    $delivery->forceFill(['delivery_status' => DeliveryStatusEnum::UNCERTAIN])->save();
    $this->post(route('central.deliveries.resend', $delivery), ['expected_state' => $delivery->managementState()])->assertForbidden();
    $this->post(route('central.deliveries.retry', $delivery), ['expected_state' => $delivery->managementState()])->assertForbidden();
});

test('corrected invoice filters agree with the original payment ledger', function (): void {
    Notification::fake();
    Queue::fake();
    $f = manualPaymentFixture();
    $this->actingAs($f['actor'], 'central');
    $this->get(route('central.invoices.index', ['filter' => 'unpaid']))->assertOk()->assertSee($f['invoice']->number);
    app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [
        'amount' => '3000.00', 'currency' => 'DZD', 'occurred_at' => now()->toDateTimeString(), 'operation_id' => (string) Str::uuid(), 'received' => true,
    ]);
    $rules = app(BillingRuleService::class);
    $rule = $rules->create($f['actor'], ['code' => 'credit.filter.test', 'trigger_event' => TriggerEventEnum::INVOICE_CORRECTION->value,
        'parameters' => ['document_type' => DocumentTypeEnum::CREDIT_NOTE->value, 'payment_due_days' => 0]], (string) Str::uuid());
    $rules->validateRule($f['actor'], $rule, $rule->managementState(), 'TEST-CREDIT');
    $rule->refresh();
    $rules->activate($f['actor'], $rule, $rule->managementState(), now()->subDay()->toDateTimeString(), null);
    $credits = app(SaasCreditNoteService::class);
    foreach ([['500.00', '0.1'], ['2500.00', '0.9']] as [$amount, $quantity]) {
        $draft = $credits->createCreditNoteDraft($f['actor'], $f['invoice'], ['billing_rule_uuid' => $rule->uuid,
            'operation_id' => (string) Str::uuid(), 'expected_state' => $f['invoice']->managementState(), 'reason' => 'Correction de test',
            'lines' => [['original_line_uuid' => $f['invoice']->lines()->sole()->uuid, 'quantity' => $quantity, 'total_amount' => $amount, 'reason' => 'Correction de test']]]);
        app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $draft, $draft->managementState());
        $this->get(route('central.invoices.index', ['filter' => 'corrected']))->assertOk()->assertSee($f['invoice']->number);
        $paid = $this->get(route('central.invoices.index', ['filter' => 'paid']))->assertOk();
        $amount === '500.00' ? $paid->assertSee($f['invoice']->number) : $paid->assertDontSee($f['invoice']->number);
        $this->get(route('central.invoices.index', ['filter' => 'unpaid']))->assertOk()->assertDontSee($f['invoice']->number);
    }
});

test('a rolled back refusal sends no notification and keeps the previous request state', function (): void {
    Notification::fake();
    Queue::fake();
    $f = administrationRequestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], administrationRequestInput($f));
    Notification::fake();
    $before = Activity::forSubject($request)->count();
    DB::beginTransaction();
    try {
        $service->reject($f['actor'], $request, $request->managementState(), 'Décision annulée par rollback');
        Notification::assertNothingSent();
    } finally {
        DB::rollBack();
    }
    expect($request->refresh()->status)->toBe(RequestStatusEnum::AWAITING_PAYMENT)
        ->and(Activity::forSubject($request)->count())->toBe($before);
    Notification::assertNothingSent();
});

test('a paid scheduled period notifies its actual activation only once', function (): void {
    Notification::fake();
    Queue::fake();
    $f = manualPaymentFixture(future: true);
    app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [
        'amount' => '3000.00', 'currency' => 'DZD', 'occurred_at' => now()->toDateTimeString(), 'operation_id' => (string) Str::uuid(), 'received' => true,
    ]);
    expect($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED);
    $this->travel(3)->days();
    $defaults = app(DefaultSubscription::class);
    $defaults->ensure($f['owner'], $f['actor']);
    $defaults->ensure($f['owner'], $f['actor']);
    expect($f['subscription']->refresh()->status)->toBe(StatusEnum::ACTIVE);
    $owner = App\Models\Central\Users\User::findOrFail($f['owner']->id);
    expect(Notification::sent($owner, SubscriptionWorkflowNotification::class,
        fn ($notice) => $notice->event === SubscriptionWorkflowNotifier::ACTIVATED)->count())->toBe(1);
});
