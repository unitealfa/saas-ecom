<?php

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasPayment;
use App\Models\User;
use App\Services\Central\Billing\OwnerPaymentCorrectionService;
use App\Services\Central\Billing\SaasPaymentService;
use Illuminate\Support\Str;

require_once dirname(__DIR__, 3).'/Support/ManualBillingFixtures.php';

it('C-157 shows only the authenticated merchant due periods and invoices', function (): void {
    $f = manualPaymentFixture();

    $this->actingAs($f['owner'], 'central')
        ->get(route('owner.billing.index'))
        ->assertOk()
        ->assertSee('Ma facturation SaaS')
        ->assertSee($f['invoice']->number)
        ->assertSee($f['due']->installment_number, false)
        ->assertSee('3000.00')
        ->assertSee('Déposer un justificatif');

    $unrelated = User::factory()->create();
    $this->actingAs($unrelated, 'central')
        ->get(route('owner.billing.index'))
        ->assertOk()
        ->assertDontSee($f['invoice']->number)
        ->assertDontSee($f['invoice']->uuid);
});

it('C-157 denies other merchants access to someone elses payment correction form', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment(
        $f['actor'],
        $f['invoice'],
        [...manualExternalPayload(), 'received' => true],
    );

    $intruder = User::factory()->create();
    $this->actingAs($intruder, 'central')
        ->get(route('owner.billing.corrections.create', $payment))->assertForbidden();

    $this->post(route('owner.billing.corrections.store', $payment), [
        'request_id' => (string) Str::uuid(),
        'reason' => 'Je demande un examen du montant indiqué.',
    ])->assertForbidden();
});

it('C-158 never treats a newly created invoice as proof of payment', function (): void {
    $f = manualPaymentFixture();

    $this->actingAs($f['owner'], 'central')
        ->get(route('owner.billing.index'))
        ->assertOk()
        ->assertSee('À payer')
        ->assertSee('Paiement intégral');

    expect(SaasPayment::where('user_id', $f['owner']->id)->count())->toBe(0);
});

it('C-158 never accepts a partial cash amount as a confirmed payment', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment(
        $f['actor'],
        $f['invoice'],
        [...manualExternalPayload('1000.00'), 'received' => true],
    );

    expect($payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::UNCERTAIN)
        ->and($payment->getAttribute('amount'))->toBe('1000.00')
        ->and($payment->getAttribute('validated_at'))->toBeNull();

    $this->actingAs($f['owner'], 'central')
        ->get(route('owner.billing.index'))
        ->assertOk()
        ->assertDontSee('Reçu privé');
});

it('C-159 creates one append-only correction request without modifying confirmed money', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment(
        $f['actor'],
        $f['invoice'],
        [...manualExternalPayload(), 'received' => true],
    );
    expect($payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::VERIFIED);

    $paymentBefore = $payment->refresh()->getAttributes();
    $invoiceBefore = $f['invoice']->refresh()->getAttributes();
    $id = (string) Str::uuid();
    $data = ['request_id' => $id, 'reason' => 'Je souhaite signaler une erreur de référence sur mon règlement.'];

    $this->actingAs($f['owner'], 'central')
        ->get(route('owner.billing.corrections.create', $payment))->assertOk()
        ->assertSee('Signaler une erreur');

    $this->post(route('owner.billing.corrections.store', $payment), $data)
        ->assertRedirect(route('owner.billing.index'));
    $this->post(route('owner.billing.corrections.store', $payment), $data)
        ->assertRedirect(route('owner.billing.index'));

    $reports = Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::EVENT)->get();
    expect($reports)->toHaveCount(1)
        ->and($reports->first()->properties['request_id'])->toBe($id)
        ->and($payment->refresh()->getAttributes())->toBe($paymentBefore)
        ->and($f['invoice']->refresh()->getAttributes())->toBe($invoiceBefore);

    $this->get(route('owner.billing.index'))->assertOk()
        ->assertSee($data['reason']);
});

it('C-159 rejects replaying the same request ID with different content', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment(
        $f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true],
    );
    $id = (string) Str::uuid();
    $this->actingAs($f['owner'], 'central');

    $this->post(route('owner.billing.corrections.store', $payment), [
        'request_id' => $id,
        'reason' => 'Je demande un premier examen de cette opération.',
    ])->assertSessionHasNoErrors();

    $this->post(route('owner.billing.corrections.store', $payment), [
        'request_id' => $id,
        'reason' => 'Je remplace la précédente raison par une autre.',
    ])->assertSessionHasErrors('request_id');

    expect(Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::EVENT)->count())->toBe(1);
});

it('C-159 rejects client attempts to alter amount or payment status through the report', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment(
        $f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true],
    );
    $this->actingAs($f['owner'], 'central')
        ->post(route('owner.billing.corrections.store', $payment), [
            'request_id' => (string) Str::uuid(),
            'reason' => 'Je veux signaler un élément sur ce paiement.',
            'amount' => '0.00',
            'transfer_status' => TransferStatusEnum::VERIFIED->value,
        ])->assertSessionHasErrors(['amount', 'transfer_status']);

    expect(Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::EVENT)->count())->toBe(0);
});

it('C-162 lists the confirmed receipt and keeps staff outside the owner overview', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment(
        $f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true],
    );

    $this->actingAs($f['owner'], 'central')
        ->get(route('owner.billing.index'))
        ->assertOk()
        ->assertSee('Reçu privé')
        ->assertSee('PDF privé')
        ->assertSee($payment->uuid);

    $this->actingAs($f['actor'], 'central')
        ->get(route('owner.billing.index'))
        ->assertForbidden();
});

it('C-159 puts the owners correction request in the authorized admin inbox and history', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment(
        $f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true],
    );
    $reason = 'La référence du versement doit être examinée par un administrateur.';

    $this->actingAs($f['owner'], 'central')
        ->post(route('owner.billing.corrections.store', $payment), [
            'request_id' => (string) Str::uuid(),
            'reason' => $reason,
        ])->assertSessionHasNoErrors();

    $this->get(route('central.payments.corrections.index'))->assertForbidden();

    $this->actingAs($f['actor'], 'central')
        ->get(route('central.payments.corrections.index'))
        ->assertOk()
        ->assertSee($reason)
        ->assertSee($payment->uuid);

    $this->get(route('central.payments.show', $payment))
        ->assertOk()
        ->assertSee($reason);
});
