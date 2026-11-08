<?php

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

use App\Enums\Central\Billing\DeliveryStatusEnum;
use App\Jobs\Central\SendSaasDocumentJob;
use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Services\Central\Billing\SaasDocumentDeliveryService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Models\Central\Roles\Role;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\SaasCreditNoteService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use Illuminate\Support\Str;

it('keeps an issued document as a pending intent until an authorized administrator requests email', function (): void {
    Queue::fake();
    $f = manualPaymentFixture();
    $delivery = SaasDocumentDelivery::sole();

    expect($delivery->delivery_status)->toBe(DeliveryStatusEnum::PENDING)
        ->and($delivery->attempts_count)->toBe(0);
    Queue::assertNotPushed(SendSaasDocumentJob::class);

    $this->actingAs($f['actor'], 'central')
        ->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect(SaasDocumentDelivery::count())->toBe(1)
        ->and($delivery->refresh()->delivery_status)->toBe(DeliveryStatusEnum::PENDING);
    Queue::assertPushed(SendSaasDocumentJob::class, 1);
});

it('does not grant delivery access to an ordinary merchant', function (): void {
    Queue::fake();
    $f = manualPaymentFixture();
    $delivery = SaasDocumentDelivery::sole();
    $this->actingAs($f['owner'], 'central')
        ->get(route('central.deliveries.index'))->assertForbidden();
    $this->get(route('central.deliveries.show', $delivery))->assertForbidden();
    $this->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])->assertForbidden();
    $this->post(route('central.deliveries.retry', $delivery), ['expected_state' => $delivery->managementState()])->assertForbidden();
    Queue::assertNotPushed(SendSaasDocumentJob::class);
});

it('refuses to send a draft document before issue', function (): void {
    Queue::fake();
    $f = manualPaymentFixture(issued: false);
    $this->actingAs($f['actor'], 'central')
        ->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])
        ->assertSessionHasErrors('document_uuid');
    expect(SaasDocumentDelivery::count())->toBe(0);
    Queue::assertNotPushed(SendSaasDocumentJob::class);
});

it('preserves a damaged PDF and allows a controlled retry without sending it', function (): void {
    Queue::fake();
    Mail::fake();
    $f = manualPaymentFixture();
    $delivery = SaasDocumentDelivery::sole();
    $this->actingAs($f['actor'], 'central')
        ->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])
        ->assertSessionHasNoErrors();
    Storage::disk('local')->delete($f['invoice']->media->storage_key);

    app(SaasDocumentDeliveryService::class)->process($delivery->id);
    expect($delivery->refresh()->delivery_status)->toBe(DeliveryStatusEnum::RETRYABLE)
        ->and($delivery->attempts_count)->toBe(1)
        ->and($delivery->sent_at)->toBeNull();
    Mail::assertNothingSent();

    $this->post(route('central.deliveries.retry', $delivery), ['expected_state' => str_repeat('0', 64)])
        ->assertSessionHasErrors('document_uuid');
    $this->post(route('central.deliveries.retry', $delivery), ['expected_state' => $delivery->managementState()])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($delivery->refresh()->delivery_status)->toBe(DeliveryStatusEnum::PENDING);
    Queue::assertPushed(SendSaasDocumentJob::class, 2);
});

it('does not duplicate sent documents or allow a resend with an uncertain outcome', function (): void {
    Queue::fake();
    $f = manualPaymentFixture();
    $delivery = SaasDocumentDelivery::sole();
    $this->actingAs($f['actor'], 'central')
        ->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])
        ->assertSessionHasNoErrors();

    $delivery->forceFill(['delivery_status' => DeliveryStatusEnum::UNCERTAIN])->save();
    $this->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])
        ->assertSessionHasErrors('document_uuid');
    $this->post(route('central.deliveries.retry', $delivery), ['expected_state' => $delivery->managementState()])
        ->assertForbidden();
    expect(SaasDocumentDelivery::count())->toBe(1);
});

it('does not modify a subscription, invoice or payment during delivery creation', function (): void {
    Queue::fake();
    $f = manualPaymentFixture();
    $invoiceAttributes = $f['invoice']->getAttributes();
    $subscriptionAttributes = $f['subscription']->getAttributes();
    $this->actingAs($f['actor'], 'central')
        ->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])
        ->assertSessionHasNoErrors();
    expect($f['invoice']->refresh()->getAttributes())->toBe($invoiceAttributes)
        ->and($f['subscription']->refresh()->getAttributes())->toBe($subscriptionAttributes);
});

it('marks the email as accepted only when the configured SMTP transport succeeds', function (): void {
    Queue::fake();
    Mail::fake();
    config(['mail.default' => 'smtp']);
    $f = manualPaymentFixture();
    $delivery = SaasDocumentDelivery::sole();
    $invoiceBefore = $f['invoice']->getAttributes();

    $this->actingAs($f['actor'], 'central')
        ->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])
        ->assertSessionHasNoErrors();
    app(SaasDocumentDeliveryService::class)->process($delivery->id);

    expect($delivery->refresh()->delivery_status)->toBe(DeliveryStatusEnum::SENT)
        ->and($delivery->sent_at)->not->toBeNull()
        ->and($delivery->attempts_count)->toBe(1)
        ->and($f['invoice']->refresh()->getAttributes())->toBe($invoiceBefore);
    $this->post(route('central.deliveries.send'), ['document_uuid' => $f['invoice']->uuid])
        ->assertSessionHasNoErrors();
    expect(SaasDocumentDelivery::count())->toBe(1);
    Queue::assertPushed(SendSaasDocumentJob::class, 1);
});


it('stops retrying after the third unsuccessful preparation', function (): void {
    Queue::fake();
    Mail::fake();
    config(['mail.default' => 'log']);

    $f = manualPaymentFixture();
    $delivery = SaasDocumentDelivery::sole();
    $this->actingAs($f['actor'], 'central');

    $this->post(route('central.deliveries.send'), [
        'document_uuid' => $f['invoice']->uuid,
    ])->assertSessionHasNoErrors();

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        app(SaasDocumentDeliveryService::class)->process($delivery->id);
        $delivery->refresh();

        expect($delivery->attempts_count)->toBe($attempt);

        if ($attempt < 3) {
            expect($delivery->delivery_status)
                ->toBe(DeliveryStatusEnum::RETRYABLE);

            $this->post(route('central.deliveries.retry', $delivery), [
                'expected_state' => $delivery->managementState(),
            ])->assertSessionHasNoErrors();
        }
    }

    expect($delivery->delivery_status)->toBe(DeliveryStatusEnum::FAILED);

    $this->post(route('central.deliveries.retry', $delivery), [
        'expected_state' => $delivery->managementState(),
    ])->assertForbidden();

    Mail::assertNothingSent();
});

it('turns an abandoned SMTP attempt into uncertain without sending again', function (): void {
    Queue::fake();
    Mail::fake();

    $f = manualPaymentFixture();
    $delivery = SaasDocumentDelivery::sole();

    $delivery->forceFill([
        'delivery_status' => DeliveryStatusEnum::SENDING,
        'attempts_count' => 1,
        'sending_started_at' => now()->subMinutes(20),
        'delivery_attempts' => [
            ['id' => 'attempt-test', 'status' => 'sending'],
        ],
    ])->save();

    expect(app(SaasDocumentDeliveryService::class)->reconcileStale())
        ->toBe(1)
        ->and($delivery->refresh()->delivery_status)
        ->toBe(DeliveryStatusEnum::UNCERTAIN)
        ->and($delivery->error_code)
        ->toBe('WORKER_RESULT_UNKNOWN')
        ->and(app(SaasDocumentDeliveryService::class)->reconcileStale())
        ->toBe(0);

    $this->actingAs($f['actor'], 'central')
        ->post(route('central.deliveries.retry', $delivery), [
            'expected_state' => $delivery->managementState(),
        ])->assertForbidden();

    Mail::assertNothingSent();
    Queue::assertNotPushed(SendSaasDocumentJob::class);
});

it('releases a queued delivery never picked up by a worker for manual retry', function (): void {
    Queue::fake();
    Mail::fake();

    $f = manualPaymentFixture();
    $delivery = SaasDocumentDelivery::sole();

    $delivery->forceFill([
        'next_attempt_at' => now()->subMinute(),
    ])->save();

    expect(app(SaasDocumentDeliveryService::class)->reconcileStale())
        ->toBe(1)
        ->and($delivery->refresh()->delivery_status)
        ->toBe(DeliveryStatusEnum::RETRYABLE)
        ->and($delivery->error_code)
        ->toBe('QUEUE_NOT_PROCESSED');

    app(SaasDocumentDeliveryService::class)->process($delivery->id);

    expect($delivery->refresh()->delivery_status)
        ->toBe(DeliveryStatusEnum::RETRYABLE);

    Mail::assertNothingSent();
});

it('keeps the three delivery permissions independent', function (): void {
    Queue::fake();

    $f = manualPaymentFixture();
    $delivery = SaasDocumentDelivery::sole();
    $reader = User::factory()->create();

    $reader->assignRole(Role::createWithPermissions(
        'delivery-reader-'.Str::random(8),
        ['saas.deliveries.view' => 7],
    ));

    $this->actingAs($reader, 'central');

    $this->get(route('central.deliveries.index'))->assertOk();
    $this->get(route('central.deliveries.show', $delivery))->assertOk();

    $this->post(route('central.deliveries.send'), [
        'document_uuid' => $f['invoice']->uuid,
    ])->assertForbidden();

    $delivery->forceFill([
        'delivery_status' => DeliveryStatusEnum::RETRYABLE,
    ])->save();

    $this->post(route('central.deliveries.retry', $delivery), [
        'expected_state' => $delivery->managementState(),
    ])->assertForbidden();

    Queue::assertNotPushed(SendSaasDocumentJob::class);
});

it('sends an issued credit note without modifying its original invoice', function (): void {
    Queue::fake();
    Mail::fake();
    config(['mail.default' => 'smtp']);

    $f = manualPaymentFixture();
    $originalState = $f['invoice']->getAttributes();

    $rules = app(BillingRuleService::class);

    $rule = $rules->create($f['actor'], [
        'code' => 'credit.delivery.'.Str::lower(Str::random(8)),
        'trigger_event' => TriggerEventEnum::INVOICE_CORRECTION->value,
        'parameters' => [
            'document_type' => DocumentTypeEnum::CREDIT_NOTE->value,
            'payment_due_days' => 0,
        ],
    ], (string) Str::uuid());

    $rules->validateRule(
        $f['actor'],
        $rule,
        $rule->managementState(),
        'TEST-CREDIT'
    );

    $rule->refresh();

    $rules->activate(
        $f['actor'],
        $rule,
        $rule->managementState(),
        now()->subDay()->toDateTimeString(),
        null
    );

    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft(
        $f['actor'],
        $f['invoice'],
        [
            'billing_rule_uuid' => $rule->uuid,
            'operation_id' => (string) Str::uuid(),
            'expected_state' => $f['invoice']->managementState(),
            'reason' => 'Correction de test',
            'lines' => [[
                'original_line_uuid' => $f['invoice']->lines()->sole()->uuid,
                'quantity' => '0.10',
                'total_amount' => '500.00',
                'reason' => 'Correction de test',
            ]],
        ]
    );

    $credit = app(SaasInvoiceIssuanceService::class)->issue(
        $f['actor'],
        $credit,
        $credit->managementState()
    );

    $delivery = SaasDocumentDelivery::where(
        'document_id',
        $credit->id
    )->sole();

    $this->actingAs($f['actor'], 'central')
        ->post(route('central.deliveries.send'), [
            'document_uuid' => $credit->uuid,
        ])->assertSessionHasNoErrors();

    app(SaasDocumentDeliveryService::class)->process($delivery->id);

    expect($delivery->refresh()->delivery_status)
        ->toBe(DeliveryStatusEnum::SENT)
        ->and($f['invoice']->refresh()->getAttributes())
        ->toBe($originalState);
});
