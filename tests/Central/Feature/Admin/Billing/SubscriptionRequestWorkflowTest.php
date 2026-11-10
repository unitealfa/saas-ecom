<?php

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Enums\Central\Subscriptions\PaymentChoiceEnum;
use App\Enums\Central\Subscriptions\RequestStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\User;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Billing\SaasCreditNoteService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasPaymentService;
use App\Services\Central\Media\PrivateMediaAccessService;
use App\Services\Central\Plans\PublicPlanCatalog;
use App\Services\Central\Subscriptions\SubscriptionRequestPaymentState;
use App\Services\Central\Subscriptions\SubscriptionRequestService;
use Database\Factories\Central\MediaFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

function requestFixture(): array
{
    $f = manualPaymentFixture(issued: false);
    $f['subscription']->forceFill(['status' => StatusEnum::CANCELLED])->save();
    $f['plan'] = Plan::factory()->create(['name' => 'Pro fictif', 'is_active' => true, 'monthly_price' => '3000.00', 'annual_price' => '36000.00']);

    return $f;
}

function subscriptionRequestInput(array $f): array
{
    return ['plan_uuid' => $f['plan']->uuid, 'period' => 1, 'expected_amount' => '3000.00',
        'payment_choice' => PaymentChoiceEnum::CASH->value, 'operation_id' => (string) Str::uuid()];
}

test('invoice and receipt open as protected printable PDFs without changing normal downloads', function (): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], subscriptionRequestInput($f));
    $invoiceUrl = URL::temporarySignedRoute('owner.invoices.download', now()->addMinutes(5), ['invoice' => $request->invoice, 'inline' => 1]);
    $response = $this->actingAs($f['owner'], 'central')->get($invoiceUrl)->assertOk()
        ->assertHeader('content-type', 'application/pdf')->assertHeader('x-content-type-options', 'nosniff')
        ->assertStreamedContent(Storage::disk('local')->get($request->invoice->media->storage_key));
    expect($response->headers->get('content-disposition'))->toStartWith('inline;');
    $download = URL::temporarySignedRoute('owner.invoices.download', now()->addMinutes(5), ['invoice' => $request->invoice]);
    expect($this->get($download)->assertOk()->headers->get('content-disposition'))->toStartWith('attachment;');
    $this->get($download.'&inline=1')->assertForbidden();

    $request = $service->accept($f['actor'], $request, $request->managementState(), [
        'amount' => '3000.00', 'received' => true, 'operation_id' => (string) Str::uuid(), 'occurred_at' => now()->toDateTimeString(),
    ]);
    foreach (['owner' => $f['owner'], 'central' => $f['actor']] as $prefix => $actor) {
        $url = URL::temporarySignedRoute($prefix.'.payments.receipt', now()->addMinutes(5), ['payment' => $request->payment, 'inline' => 1]);
        $response = $this->actingAs($actor, 'central')->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        expect($response->headers->get('content-disposition'))->toStartWith('inline;');
    }
    $this->actingAs(User::factory()->create(), 'central')->get($invoiceUrl)->assertForbidden();
    expect($request->status)->toBe(RequestStatusEnum::ACCEPTED)->and(SaasPayment::count())->toBe(1);
});

test('T01 T02 a request has its real invoice before payment without activating paid rights', function (): void {
    $f = requestFixture();
    $request = app(SubscriptionRequestService::class)->create($f['owner'], subscriptionRequestInput($f));
    expect($request)->toBeInstanceOf(SubscriptionRequest::class)
        ->and($request->user_id)->toBe($f['owner']->id)
        ->and($request->status)->toBe(RequestStatusEnum::AWAITING_PAYMENT)
        ->and($request->subscription->agreed_amount)->toBe('3000.00')
        ->and($request->subscription->status)->toBe(StatusEnum::SCHEDULED)
        ->and($request->invoice->status)->toBe(DocumentStatusEnum::ISSUED)
        ->and($request->invoice->total_amount)->toBe('3000.00')
        ->and(Storage::disk('local')->get($request->invoice->media->storage_key))->toStartWith('%PDF-')
        ->and(SaasPayment::count())->toBe(0);
});

test('missing fiscal configuration preserves the request and a retry issues only one invoice', function (): void {
    $f = requestFixture();
    $issuer = config('central.billing.issuer');
    config(['central.billing.issuer.nif' => null]);
    $input = subscriptionRequestInput($f);
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], $input);
    expect($request->status)->toBe(RequestStatusEnum::INVOICE_PENDING)->and($request->invoice)->toBeNull()
        ->and($request->getAttribute('invoice_error'))->not->toBeNull()->and($request->subscription->status)->toBe(StatusEnum::SCHEDULED);
    config(['central.billing.issuer' => $issuer]);
    $request = $service->prepareInvoice($f['owner'], $request, $request->managementState());
    $replay = $service->create($f['owner'], $input);
    expect($request->status)->toBe(RequestStatusEnum::AWAITING_PAYMENT)->and($replay->id)->toBe($request->id)
        ->and($replay->current_invoice_id)->toBe($request->current_invoice_id)->and($request->periods()->count())->toBe(1);
});

test('the request invoice authorization never grants a customer administrative invoice rights', function (): void {
    $f = requestFixture();
    $request = app(SubscriptionRequestService::class)->create($f['owner'], subscriptionRequestInput($f));
    expect(Gate::forUser($f['owner'])->allows('issue', $request->invoice))->toBeFalse()
        ->and(Gate::forUser($f['owner'])->allows('view', $request->invoice))->toBeFalse();
    $this->actingAs($f['owner'], 'central')->post(route('central.invoices.issue', $request->invoice), ['expected_state' => $request->invoice->managementState()])->assertForbidden();
    $this->get(route('owner.invoices.show', $request->invoice))->assertOk()->assertSee($request->invoice->number);
});

test('T08 T10 cash acceptance confirms the full payment and request together without bank details', function (): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], subscriptionRequestInput($f));
    $request = $service->accept($f['actor'], $request, $request->managementState(), [
        'amount' => '3000.00', 'received' => true, 'operation_id' => (string) Str::uuid(), 'occurred_at' => now()->toDateTimeString(),
    ]);
    expect($request->status)->toBe(RequestStatusEnum::ACCEPTED)
        ->and($request->payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::VERIFIED)
        ->and($request->subscription->status)->toBe(StatusEnum::ACTIVE)
        ->and($request->payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->exists())->toBeTrue();
});

test('T11 T23 an external refusal preserves the old proof and allows a new declaration', function (): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], [...subscriptionRequestInput($f), 'payment_choice' => PaymentChoiceEnum::EXTERNAL->value]);
    $proof = fn () => UploadedFile::fake()->image('preuve.png');
    $request = $service->submitProof($f['owner'], $request, $request->managementState(), manualExternalPayload(), $proof());
    $previous = $request->payment;
    expect($request->status)->toBe(RequestStatusEnum::REVIEW)->and($request->subscription->status)->toBe(StatusEnum::SCHEDULED);
    $request = $service->reject($f['actor'], $request, $request->managementState(), 'Justificatif non confirmé', $previous->managementState());
    expect($request->status)->toBe(RequestStatusEnum::REJECTED)->and($previous->refresh()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::REJECTED);
    $request = $service->submitProof($f['owner'], $request, $request->managementState(), manualExternalPayload(), $proof());
    expect($request->status)->toBe(RequestStatusEnum::REVIEW)->and($request->current_payment_id)->not->toBe($previous->getKey())
        ->and($previous->documents()->exists())->toBeTrue()->and($request->getAttribute('decision_reason'))->toBeNull();
    $request = $service->accept($f['actor'], $request, $request->managementState(), [
        ...manualVerification($request->payment), 'expected_payment_state' => $request->payment->managementState(),
    ]);
    expect($request->status)->toBe(RequestStatusEnum::ACCEPTED)->and($request->subscription->status)->toBe(StatusEnum::ACTIVE);
});

test('T36 refusal requires a reason and a stale decision cannot change the request', function (): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], subscriptionRequestInput($f));
    $expected = $request->managementState();
    expect(fn () => $service->reject($f['actor'], $request, $expected, ''))->toThrow(ValidationException::class);
    $request = $service->reject($f['actor'], $request, $expected, 'Demande à corriger');
    expect(fn () => $service->accept($f['actor'], $request, $expected, []))->toThrow(ValidationException::class);
    expect($request->refresh()->status)->toBe(RequestStatusEnum::REJECTED)->and(SaasPayment::count())->toBe(0);
});

test('T20 T21 T22 changing the payment method or proof invalidates the previous decision', function (): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], subscriptionRequestInput($f));
    $invoice = $request->current_invoice_id;
    $request = $service->changePaymentChoice($f['owner'], $request, $request->managementState(), PaymentChoiceEnum::EXTERNAL);
    $request = $service->submitProof($f['owner'], $request, $request->managementState(), manualExternalPayload(), UploadedFile::fake()->image('avant.png'));
    $oldPayment = $request->payment;
    $oldState = $request->managementState();
    $request = $service->submitProof($f['owner'], $request, $oldState, manualExternalPayload(), UploadedFile::fake()->image('apres.png'));
    expect($request->current_payment_id)->not->toBe($oldPayment->getKey())
        ->and($oldPayment->refresh()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::CANCELLED)
        ->and($oldPayment->documents()->exists())->toBeTrue();
    expect(fn () => $service->accept($f['actor'], $request, $oldState, manualVerification($oldPayment)))->toThrow(ValidationException::class);
    $request = $service->changePaymentChoice($f['owner'], $request, $request->managementState(), PaymentChoiceEnum::CASH);
    expect($request->current_payment_id)->toBeNull()->and($request->current_invoice_id)->toBe($invoice)
        ->and($request->status)->toBe(RequestStatusEnum::AWAITING_PAYMENT);
});

test('the owner and administrative request pages are connected and isolated', function (): void {
    $f = requestFixture();
    $this->actingAs($f['owner'], 'central')->post(route('owner.subscriptions.store'), subscriptionRequestInput($f))->assertSessionHasNoErrors()->assertRedirect();
    $request = SubscriptionRequest::sole();
    $this->get(route('owner.subscription-requests.index'))->assertOk()->assertSee('Mes demandes d’abonnement');
    $this->get('http://localhost/demandes-abonnement')->assertOk()->assertSee('Mes demandes d’abonnement');
    $this->get(route('owner.subscription-requests.show', $request))->assertOk()->assertSee($request->invoice->number)->assertSee('Modifier l’offre');
    $this->get(route('central.subscription-requests.index'))->assertForbidden();
    $this->post(route('central.subscription-requests.accept', $request), [])->assertForbidden();
    $this->actingAs($f['actor'], 'central')->get(route('central.subscription-requests.index'))->assertOk()->assertSee('Demandes');
    $this->get(route('central.subscription-requests.show', $request))->assertOk()->assertSee('Confirmer les fonds');
    $this->post(route('central.subscription-requests.accept', $request), [
        'expected_state' => $request->managementState(), 'amount' => '3000.00', 'currency' => 'DZD', 'received' => true,
        'occurred_at' => now()->toDateTimeString(), 'operation_id' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors()->assertRedirect(route('central.subscription-requests.show', $request));
    expect($request->refresh()->status)->toBe(RequestStatusEnum::ACCEPTED);
    $this->actingAs($f['owner'], 'central')->get(route('owner.subscription-requests.show', $request))->assertOk()->assertDontSee('Modifier l’offre')->assertSee('Ouvrir le reçu');
});

test('T18 T26 an issued invoice blocks price changes or cancellation until manual correction', function (): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], subscriptionRequestInput($f));
    $other = Plan::factory()->create(['monthly_price' => '5000.00', 'is_active' => true]);
    expect(fn () => $service->revise($f['owner'], $request, $request->managementState(), [
        ...subscriptionRequestInput($f), 'plan_uuid' => $other->uuid, 'expected_amount' => '5000.00',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $service->cancel($f['owner'], $request, $request->managementState()))->toThrow(ValidationException::class);
    expect($request->refresh()->invoice->total_amount)->toBe('3000.00')
        ->and(SaasCreditNote::count())->toBe(0)
        ->and($request->periods()->count())->toBe(1);
});

test('T18 T19 a manually issued full correction permits a new invoice without rewriting the old one', function (bool $changePlan): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], subscriptionRequestInput($f));
    $original = $request->invoice;
    $originalBytes = Storage::disk('local')->get($original->media->storage_key);
    $rules = app(BillingRuleService::class);
    $rule = $rules->create($f['actor'], ['code' => 'request.correction', 'trigger_event' => TriggerEventEnum::INVOICE_CORRECTION->value,
        'parameters' => ['document_type' => 2, 'payment_due_days' => 0]], (string) Str::uuid());
    $rules->validateRule($f['actor'], $rule, $rule->managementState(), 'TEST-CORRECTION');
    $rule->refresh();
    $rules->activate($f['actor'], $rule, $rule->managementState(), now()->subDay()->toDateTimeString(), null);
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $original, [
        'billing_rule_uuid' => $rule->uuid, 'operation_id' => (string) Str::uuid(), 'expected_state' => $original->managementState(),
        'reason' => 'Correction documentaire manuelle avant changement de période', 'lines' => [[
            'original_line_uuid' => $original->lines()->sole()->uuid, 'quantity' => '1.00', 'total_amount' => '3000.00', 'reason' => 'Correction intégrale autorisée',
        ]],
    ]);
    app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    $plan = $changePlan ? Plan::factory()->create(['monthly_price' => '5000.00', 'is_active' => true]) : $f['plan'];
    $amount = $changePlan ? '5000.00' : '36000.00';
    $request = $service->revise($f['owner'], $request, $request->managementState(), [
        ...subscriptionRequestInput($f), 'plan_uuid' => $plan->uuid, 'period' => $changePlan ? 1 : 2, 'expected_amount' => $amount,
    ]);
    expect($request->invoice->total_amount)->toBe($amount)->and($request->current_invoice_id)->not->toBe($original->id)
        ->and($request->periods()->count())->toBe(2)->and($request->subscription->status)->toBe(StatusEnum::SCHEDULED)
        ->and($original->refresh()->status)->toBe(DocumentStatusEnum::ISSUED)
        ->and(Storage::disk('local')->get($original->media->storage_key))->toBe($originalBytes);
    expect(fn () => app(SaasPaymentService::class)->recordCashPayment($f['actor'], $original, [
        'amount' => '3000.00', 'currency' => 'DZD', 'received' => true, 'operation_id' => (string) Str::uuid(), 'occurred_at' => now()->toDateTimeString(),
        'expected_request_state' => $request->managementState(),
    ]))->toThrow(ValidationException::class);
})->with(['plan' => [true], 'period' => [false]]);

test('T16 an invalid replacement proof does not discard the current valid declaration', function (): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], [...subscriptionRequestInput($f), 'payment_choice' => PaymentChoiceEnum::EXTERNAL->value]);
    $request = $service->submitProof($f['owner'], $request, $request->managementState(), manualExternalPayload(), UploadedFile::fake()->image('valide.png'));
    $paymentId = $request->current_payment_id;
    expect(fn () => $service->submitProof($f['owner'], $request, $request->managementState(), manualExternalPayload(),
        UploadedFile::fake()->createWithContent('invalide.pdf', '%PDF-1.4 sans fin valide')))->toThrow(ValidationException::class);
    expect($request->refresh()->current_payment_id)->toBe($paymentId)
        ->and($request->payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::DECLARED);
});

test('T03 T05 private request invoice downloads reject foreign owners and unsigned URLs', function (): void {
    $f = requestFixture();
    $request = app(SubscriptionRequestService::class)->create($f['owner'], subscriptionRequestInput($f));
    $url = URL::temporarySignedRoute('owner.invoices.download', now()->addMinutes(5), ['invoice' => $request->invoice]);
    $this->actingAs($f['owner'], 'central')->get($url)->assertOk()
        ->assertStreamedContent(Storage::disk('local')->get($request->invoice->media->storage_key));
    $this->get(route('owner.invoices.download', $request->invoice))->assertForbidden();
    $this->actingAs(User::factory()->create(), 'central')->get($url)->assertForbidden();
    $this->get(route('owner.subscription-requests.show', $request))->assertForbidden();
});

test('T37 administrative request actions require their server permissions', function (): void {
    $f = requestFixture();
    $request = app(SubscriptionRequestService::class)->create($f['owner'], subscriptionRequestInput($f));
    $this->actingAs(User::factory()->create(), 'central');
    $this->get(route('central.subscription-requests.index'))->assertForbidden();
    $this->get(route('central.subscription-requests.show', $request))->assertForbidden();
    foreach (['accept', 'reject', 'invoice'] as $action) {
        $this->post(route('central.subscription-requests.'.$action, $request), [])->assertForbidden();
    }
    expect($request->refresh()->status)->toBe(RequestStatusEnum::AWAITING_PAYMENT)->and(SaasPayment::count())->toBe(0);
});

test('T26 an unissued request can be cancelled without losing its reservation history', function (): void {
    $f = requestFixture();
    config(['central.billing.issuer.nif' => null]);
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], subscriptionRequestInput($f));
    $request = $service->cancel($f['owner'], $request, $request->managementState());
    expect($request->status)->toBe(RequestStatusEnum::CANCELLED)->and($request->invoice)->toBeNull()
        ->and($request->subscription->status)->toBe(StatusEnum::CANCELLED)->and($request->periods()->count())->toBe(1)
        ->and(SaasPayment::count())->toBe(0);
});

test('T15 a wrong declared amount without received funds remains correctable and its anomaly is retained', function (): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], [...subscriptionRequestInput($f), 'payment_choice' => PaymentChoiceEnum::EXTERNAL->value]);
    $request = $service->submitProof($f['owner'], $request, $request->managementState(), manualExternalPayload('2000.00'), UploadedFile::fake()->image('montant-incorrect.png'));
    $previous = $request->payment;
    expect($previous->getAttribute('transfer_status'))->toBe(TransferStatusEnum::UNCERTAIN)->and($previous->hasReportedFunds())->toBeFalse();
    $request = $service->reject($f['actor'], $request, $request->managementState(), 'Montant déclaré incorrect', $previous->managementState());
    $request = $service->submitProof($f['owner'], $request, $request->managementState(), manualExternalPayload(), UploadedFile::fake()->image('montant-corrige.png'));
    expect($request->status)->toBe(RequestStatusEnum::REVIEW)->and($request->current_payment_id)->not->toBe($previous->getKey())
        ->and($previous->refresh()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::UNCERTAIN)
        ->and($previous->documents()->exists())->toBeTrue()->and($request->subscription->status)->toBe(StatusEnum::SCHEDULED);
});

test('T09 insufficient cash is retained and cannot be erased by a merchant correction or refusal', function (): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], subscriptionRequestInput($f));
    $request = $service->accept($f['actor'], $request, $request->managementState(), [
        'amount' => '2000.00', 'received' => true, 'operation_id' => (string) Str::uuid(), 'occurred_at' => now()->toDateTimeString(),
    ]);
    expect($request->status)->toBe(RequestStatusEnum::REVIEW)->and($request->payment->hasReportedFunds())->toBeTrue()
        ->and($request->subscription->status)->toBe(StatusEnum::SCHEDULED);
    expect(Gate::forUser($f['owner'])->allows('update', $request))->toBeFalse()
        ->and(Gate::forUser($f['actor'])->allows('reject', $request))->toBeFalse();
    expect(fn () => $service->changePaymentChoice($f['owner'], $request, $request->managementState(), PaymentChoiceEnum::EXTERNAL))
        ->toThrow(AuthorizationException::class);
    expect($request->payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->count())->toBe(0);
});

test('T46 T47 T48 no commercial refund endpoint or action is available', function (): void {
    $f = requestFixture();
    $request = app(SubscriptionRequestService::class)->create($f['owner'], subscriptionRequestInput($f));
    $routes = collect(Route::getRoutes()->getRoutes());
    expect($routes->contains(fn ($route): bool => preg_match('/refund|rembours/i', $route->uri().' '.$route->getActionName()) === 1))->toBeFalse();
    $this->actingAs($f['actor'], 'central')->get(route('central.subscription-requests.show', $request))->assertOk()->assertDontSee('Rembourser');
    $this->post('/admin/billing/payments/'.$request->uuid.'/refund')->assertNotFound();
    expect(SaasPayment::withoutGlobalScope('payment')->where('record_type', 2)->count())->toBe(0);
});

test('T43 a receipt failure after confirmation never retries the financial acceptance', function (): void {
    $f = requestFixture();
    $request = app(SubscriptionRequestService::class)->create($f['owner'], subscriptionRequestInput($f));
    $documents = Mockery::mock(PaymentDocumentService::class, [app(PrivateMediaAccessService::class)])->makePartial();
    $documents->shouldReceive('generatePaymentReceipt')->once()->andThrow(ValidationException::withMessages(['proof' => 'Stockage fictivement indisponible']));
    $this->app->instance(PaymentDocumentService::class, $documents);
    $request = app(SubscriptionRequestService::class)->accept($f['actor'], $request, $request->managementState(), [
        'amount' => '3000.00', 'received' => true, 'operation_id' => (string) Str::uuid(), 'occurred_at' => now()->toDateTimeString(),
    ]);
    expect($request->status)->toBe(RequestStatusEnum::ACCEPTED)->and($request->payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::VERIFIED)
        ->and($request->subscription->status)->toBe(StatusEnum::ACTIVE)
        ->and($request->payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->count())->toBe(0);
    $this->app->forgetInstance(PaymentDocumentService::class);
    $receipt = app(PaymentDocumentService::class)->generatePaymentReceipt($f['actor'], $request->payment);
    expect(Storage::disk('local')->get($receipt->storage_key))->toStartWith('%PDF-')->and(SaasPayment::count())->toBe(1);
    expect(fn () => DB::table('subscription_requests')->where('id', $request->id)->update(['payment_choice' => PaymentChoiceEnum::EXTERNAL->value]))
        ->toThrow(QueryException::class);
});

test('T42 a SQL error accepting the request rolls back payment rights and decision together', function (): void {
    $f = requestFixture();
    $service = app(SubscriptionRequestService::class);
    $request = $service->create($f['owner'], [...subscriptionRequestInput($f), 'payment_choice' => PaymentChoiceEnum::EXTERNAL->value]);
    $request = $service->submitProof($f['owner'], $request, $request->managementState(), manualExternalPayload(), UploadedFile::fake()->image('rollback.png'));
    $dispatcher = Activity::getEventDispatcher();
    $event = 'eloquent.created: '.Activity::class;
    $listeners = $dispatcher->getRawListeners()[$event] ?? [];
    Activity::created(function (Activity $activity): void {
        if ($activity->getAttribute('event') === SubscriptionRequestPaymentState::ACCEPTED) {
            DB::statement("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Isolated rollback test'");
        }
    });
    try {
        expect(fn () => $service->accept($f['actor'], $request, $request->managementState(), [
            ...manualVerification($request->payment), 'expected_payment_state' => $request->payment->managementState(),
        ]))->toThrow(QueryException::class);
    } finally {
        $dispatcher->forget($event);
        foreach ($listeners as $listener) {
            $dispatcher->listen($event, $listener);
        }
    }
    expect($request->refresh()->status)->toBe(RequestStatusEnum::REVIEW)
        ->and($request->subscription->status)->toBe(StatusEnum::SCHEDULED)
        ->and($request->payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::DECLARED)
        ->and(Activity::where('event', SubscriptionRequestPaymentState::ACCEPTED)->count())->toBe(0);
});

test('public plan feature presentation is cached and invalidated after committed edits', function (): void {
    $f = requestFixture();
    $feature = Feature::factory()->create(['name' => 'Boutiques fictives', 'is_active' => true,
        'value_type' => ValueTypeEnum::QUANTITY]);
    $link = PlanFeature::create(['plan_id' => $f['plan']->id, 'feature_id' => $feature->id, 'is_active' => true, 'limit' => 3]);
    $catalog = app(PublicPlanCatalog::class);
    DB::commit();
    try {
        $catalog->invalidate();
        DB::enableQueryLog();
        $first = $catalog->available();
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);
        DB::flushQueryLog();
        expect($catalog->available())->toBe($first)->and(DB::getQueryLog())->toBe([]);
        DB::disableQueryLog();
        expect(collect($first)->firstWhere('uuid', $f['plan']->uuid)['features'])->toBe([['name' => 'Boutiques fictives', 'value' => '3']]);
        $link->update(['limit' => 4]);
        $feature->update(['name' => 'Boutiques corrigées']);
        expect(collect($catalog->available())->firstWhere('uuid', $f['plan']->uuid)['features'])->toBe([['name' => 'Boutiques corrigées', 'value' => '4']]);
    } finally {
        DB::disableQueryLog();
        DB::beginTransaction();
    }
});

test('T25 T35 two independent processes cannot accept an obsolete request or activate it twice', function (bool $edit): void {
    $f = requestFixture();
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole(Role::findByName('root', 'central'));
    $request = app(SubscriptionRequestService::class)->create($f['owner'], subscriptionRequestInput($f));
    $database = DB::connection()->getDatabaseName();
    expect($database)->toStartWith('aydra_test_');
    $runtime = storage_path('framework/testing/request-race-'.Str::uuid());
    File::ensureDirectoryExists($runtime);
    $connection = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'DB_URL' => false, 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
        'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'],
        'DB_PASSWORD' => $connection['password'], 'CACHE_STORE' => 'array', 'APP_CONFIG_CACHE' => $runtime.'/config.php',
        'APP_ROUTES_CACHE' => $runtime.'/routes.php', 'REQUEST_RUNTIME' => $runtime, 'REQUEST_UUID' => $request->uuid,
        'REQUEST_EXPECTED' => $request->managementState(), 'REQUEST_STORAGE' => Storage::disk('local')->path('')];
    $script = <<<'PHP'
    require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $c = config('database.connections.mysql');
    if (!in_array($c['host'], ['127.0.0.1','localhost','::1'], true) || !str_starts_with($c['database'], 'aydra_test_') || !empty($c['url'])) { throw new LogicException('Isolated local database required.'); }
    config(['filesystems.disks.local' => ['driver'=>'local','root'=>getenv('REQUEST_STORAGE'),'throw'=>true]]);
    $actor = App\Models\User::where('uuid', getenv('REQUEST_ACTOR'))->firstOrFail();
    $request = App\Models\Central\Subscriptions\SubscriptionRequest::where('uuid', getenv('REQUEST_UUID'))->firstOrFail();
    $service = app(App\Services\Central\Subscriptions\SubscriptionRequestService::class);
    file_put_contents(getenv('REQUEST_RUNTIME').'/ready-'.getmypid(), 'ready');
    $deadline = microtime(true) + 20;
    while (!is_file(getenv('REQUEST_RUNTIME').'/go') && microtime(true) < $deadline) { usleep(10000); }
    if (!is_file(getenv('REQUEST_RUNTIME').'/go')) { throw new RuntimeException('Race barrier timeout'); }
    try {
        if (getenv('REQUEST_ACTION') === 'edit') {
            $service->changePaymentChoice($actor, $request, getenv('REQUEST_EXPECTED'), App\Enums\Central\Subscriptions\PaymentChoiceEnum::EXTERNAL);
        } else {
            $service->accept($actor, $request, getenv('REQUEST_EXPECTED'), json_decode(getenv('REQUEST_DATA'), true, flags: JSON_THROW_ON_ERROR));
        }
        echo 'applied';
    } catch (Illuminate\Validation\ValidationException|Illuminate\Auth\Access\AuthorizationException $e) { echo 'blocked'; }
    PHP;
    $data = ['amount' => '3000.00', 'currency' => 'DZD', 'received' => true, 'operation_id' => (string) Str::uuid(), 'occurred_at' => now()->toDateTimeString()];
    DB::commit();
    $processes = [];
    try {
        foreach (['accept', $edit ? 'edit' : 'accept'] as $index => $action) {
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), [...$environment,
                'REQUEST_ACTION' => $action, 'REQUEST_ACTOR' => ($action === 'edit' ? $f['owner'] : ($index === 0 ? $f['actor'] : $otherAdmin))->uuid,
                'REQUEST_DATA' => json_encode($data, JSON_THROW_ON_ERROR)]);
            $process->setTimeout(90)->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 20;
        while (count(glob($runtime.'/ready-*')) < 2 && microtime(true) < $deadline) {
            usleep(10000);
        }
        expect(count(glob($runtime.'/ready-*')))->toBe(2);
        File::put($runtime.'/go', 'go');
        $outcomes = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
            $outcomes[] = trim($process->getOutput());
        }
        expect($outcomes)->toContain('applied');
        if ($edit) {
            expect($outcomes)->toContain('blocked');
        }
        $request->refresh();
        if ($request->status === RequestStatusEnum::ACCEPTED) {
            expect(SaasPayment::count())->toBe(1)->and($request->subscription->status)->toBe(StatusEnum::ACTIVE);
            expect(Activity::where('event', SubscriptionRequestPaymentState::ACCEPTED)->count())->toBe(1);
        } else {
            expect($request->payment_choice)->toBe(PaymentChoiceEnum::EXTERNAL)->and(SaasPayment::count())->toBe(0)
                ->and($request->subscription->status)->toBe(StatusEnum::SCHEDULED);
        }
    } finally {
        foreach ($processes as $process) {
            $process->stop();
        }
        File::deleteDirectory($runtime);
        DB::beginTransaction();
    }
})->with([false, true]);

test('a browser reads the invoice before payment and the receipt after request acceptance', function (bool $cash): void {
    $f = requestFixture();
    $request = app(SubscriptionRequestService::class)->create($f['owner'], [...subscriptionRequestInput($f),
        'payment_choice' => ($cash ? PaymentChoiceEnum::CASH : PaymentChoiceEnum::EXTERNAL)->value]);
    $runtime = storage_path('framework/testing/module22-browser-'.Str::uuid());
    foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private'] as $directory) {
        File::ensureDirectoryExists($runtime.'/'.$directory);
    }
    File::copyDirectory(Storage::disk('local')->path(''), $runtime.'/app/private');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
    expect($socket)->not->toBeFalse();
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr($address, ':'), 1);
    $url = 'http://127.0.0.1:'.$port;
    $c = config('database.connections.mysql');
    expect(DB::connection()->getDatabaseName())->toStartWith('aydra_test_');
    $environment = ['APP_ENV' => 'testing', 'APP_URL' => $url, 'SAAS_BASE_DOMAIN' => '127.0.0.1',
        'DB_CONNECTION' => 'mysql', 'DB_URL' => false, 'DB_DATABASE' => DB::connection()->getDatabaseName(),
        'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => '(null)',
        'SESSION_COOKIE' => 'module22_'.Str::random(12), 'LARAVEL_STORAGE_PATH' => $runtime, 'MAIL_MAILER' => 'log',
        'APP_CONFIG_CACHE' => $runtime.'/config.php', 'APP_ROUTES_CACHE' => $runtime.'/routes.php'];
    DB::commit();
    $server = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$port, '--no-reload', '--no-interaction'], base_path(), $environment);
    $server->setTimeout(null);
    try {
        $server->start();
        File::put($runtime.'/proof.pdf', MediaFactory::PDF_CONTENT);
        File::put($runtime.'/ready.json', json_encode(['url' => $url, 'request' => $request->uuid,
            'cash' => $cash, 'proof' => $runtime.'/proof.pdf', 'admin' => $f['actor']->email, 'owner' => $f['owner']->email, 'completion' => $runtime.'/complete.json'], JSON_THROW_ON_ERROR));
        $deadline = microtime(true) + 900;
        while (! is_file($runtime.'/complete.json') && microtime(true) < $deadline && $server->isRunning()) {
            usleep(200000);
        }
        expect(is_file($runtime.'/complete.json'))->toBeTrue($server->getErrorOutput());
        expect($request->refresh()->status)->toBe(RequestStatusEnum::ACCEPTED)
            ->and(SaasPayment::where('transfer_status', TransferStatusEnum::VERIFIED)->count())->toBe(1)
            ->and($request->subscription->status)->toBe(StatusEnum::ACTIVE);
    } finally {
        $server->stop();
        DB::beginTransaction();
        File::deleteDirectory($runtime);
    }
})->with(['cash' => [true], 'external' => [false]])
    ->skip(fn (): bool => getenv('AYDRA_MODULE22_BROWSER') !== '1', 'Requires the isolated interactive browser pilot.');
