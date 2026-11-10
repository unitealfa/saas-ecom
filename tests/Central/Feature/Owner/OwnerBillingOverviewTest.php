<?php

use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Roles\Role;
use App\Models\User;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\OwnerPaymentCorrectionService;
use App\Services\Central\Billing\SaasCreditNoteService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasPaymentService;
use Illuminate\Support\Str;

require_once dirname(__DIR__, 3).'/Support/ManualBillingFixtures.php';

function ownerBillingCreditNote(User $actor, SaasInvoice $invoice, bool $issue = true): SaasCreditNote
{
    $rules = app(BillingRuleService::class);
    $rule = $rules->create($actor, [
        'code' => 'owner.credit.test.'.Str::lower(Str::random(8)),
        'trigger_event' => TriggerEventEnum::INVOICE_CORRECTION->value,
        'parameters' => ['document_type' => DocumentTypeEnum::CREDIT_NOTE->value, 'payment_due_days' => 0],
    ], (string) Str::uuid());
    $rules->validateRule($actor, $rule, $rule->managementState(), 'TEST-CREDIT');
    $rule->refresh();
    $rules->activate($actor, $rule, $rule->managementState(), now()->subDay()->toDateTimeString(), null);

    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($actor, $invoice, [
        'billing_rule_uuid' => $rule->uuid,
        'operation_id' => (string) Str::uuid(),
        'expected_state' => $invoice->managementState(),
        'reason' => 'Correction de test explicitement émise',
        'lines' => [[
            'original_line_uuid' => $invoice->lines()->sole()->uuid,
            'quantity' => '1.00',
            'total_amount' => $invoice->total_amount,
            'reason' => 'Correction de test',
        ]],
    ]);

    if (! $issue) {
        return $credit;
    }

    $issued = app(SaasInvoiceIssuanceService::class)->issue($actor, $credit, $credit->managementState());

    return SaasCreditNote::query()->findOrFail($issued->getKey());
}

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

it('C-159 records an examined decision in history without changing the payment', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment(
        $f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true],
    );
    $reason = 'La référence du versement doit être examinée par un administrateur.';
    $paymentBefore = $payment->refresh()->getAttributes();
    $invoiceBefore = $f['invoice']->refresh()->getAttributes();

    $this->actingAs($f['owner'], 'central')
        ->post(route('owner.billing.corrections.store', $payment), [
            'request_id' => (string) Str::uuid(),
            'reason' => $reason,
        ])->assertRedirect(route('owner.billing.index'));
    $report = Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::EVENT)->sole();

    $this->get(route('owner.billing.index'))->assertOk()->assertSee('En attente');
    $this->actingAs($f['actor'], 'central')
        ->get(route('central.payments.corrections.index'))
        ->assertOk()
        ->assertSee($reason)
        ->assertSee('Examiné — aucune correction nécessaire');
    $this->post(route('central.payments.corrections.review', $report), [
        'decision' => OwnerPaymentCorrectionService::DECISION_EXAMINED,
        'note' => 'Examen effectué, aucune correction n’est nécessaire.',
    ])->assertRedirect(route('central.payments.corrections.index'));
    $this->post(route('central.payments.corrections.review', $report), [
        'decision' => OwnerPaymentCorrectionService::DECISION_EXAMINED,
        'note' => 'Examen effectué, aucune correction n’est nécessaire.',
    ])->assertSessionHasErrors('decision');
    $this->post(route('central.payments.corrections.review', $report), [
        'decision' => OwnerPaymentCorrectionService::DECISION_CREDIT_NOTE,
        'note' => 'Cette correction ne doit pas remplacer la décision.',
        'credit_uuid' => (string) Str::uuid(),
    ])->assertSessionHasErrors('decision');

    $review = Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::REVIEWED_EVENT)->sole();
    expect($review->causer_id)->toBe($f['actor']->id)
        ->and($review->properties['report_uuid'])->toBe($report->uuid)
        ->and($review->properties['decision'])->toBe(OwnerPaymentCorrectionService::DECISION_EXAMINED)
        ->and($review->properties['note'])->toBe('Examen effectué, aucune correction n’est nécessaire.')
        ->and(Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::REVIEWED_EVENT)->count())->toBe(1)
        ->and($payment->refresh()->getAttributes())->toBe($paymentBefore)
        ->and($f['invoice']->refresh()->getAttributes())->toBe($invoiceBefore);

    $this->get(route('central.payments.corrections.index'))
        ->assertOk()
        ->assertSee('Examen effectué, aucune correction n’est nécessaire.')
        ->assertSee('Examiné — aucune correction nécessaire');

    $this->actingAs($f['owner'], 'central')
        ->get(route('owner.billing.index'))
        ->assertOk()
        ->assertSee($reason)
        ->assertSee('Examen effectué, aucune correction n’est nécessaire.')
        ->assertSee('Examiné — aucune correction nécessaire');
});

it('C-159 only records an issued credit note for the payment invoice and owner', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment(
        $f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true],
    );
    $this->actingAs($f['owner'], 'central')
        ->post(route('owner.billing.corrections.store', $payment), [
            'request_id' => (string) Str::uuid(),
            'reason' => 'La référence de cette facture doit être corrigée.',
        ])->assertRedirect(route('owner.billing.index'));
    $report = Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::EVENT)->sole();

    $foreign = manualPaymentFixture();
    $draftCredit = ownerBillingCreditNote($f['actor'], $f['invoice'], issue: false);
    $foreignCredit = ownerBillingCreditNote($foreign['actor'], $foreign['invoice']);
    $paymentBefore = $payment->refresh()->getAttributes();
    $invoiceBefore = $f['invoice']->refresh()->getAttributes();

    $this->actingAs($f['actor'], 'central')
        ->post(route('central.payments.corrections.review', $report), [
            'decision' => OwnerPaymentCorrectionService::DECISION_CREDIT_NOTE,
            'note' => 'Le signalement ne peut pas encore être corrigé.',
            'credit_uuid' => $draftCredit->uuid,
        ])->assertSessionHasErrors('credit_uuid');
    $this->actingAs($f['actor'], 'central')
        ->post(route('central.payments.corrections.review', $report), [
            'decision' => OwnerPaymentCorrectionService::DECISION_CREDIT_NOTE,
            'note' => 'Cet avoir ne correspond pas au paiement signalé.',
            'credit_uuid' => $foreignCredit->uuid,
        ])->assertSessionHasErrors('credit_uuid');
    expect(Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::REVIEWED_EVENT)->count())->toBe(0);

    $issued = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $draftCredit, $draftCredit->managementState());
    $credit = SaasCreditNote::query()->findOrFail($issued->getKey());
    $this->post(route('central.payments.corrections.review', $report), [
        'decision' => OwnerPaymentCorrectionService::DECISION_CREDIT_NOTE,
        'note' => 'Le montant est corrigé par l’avoir émis sur cette facture.',
        'credit_uuid' => $credit->uuid,
    ])->assertRedirect(route('central.payments.corrections.index'));

    $review = Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::REVIEWED_EVENT)->sole();
    expect($review->properties['decision'])->toBe(OwnerPaymentCorrectionService::DECISION_CREDIT_NOTE)
        ->and($review->properties['credit_uuid'])->toBe($credit->uuid)
        ->and($review->properties['note'])->toBe('Le montant est corrigé par l’avoir émis sur cette facture.')
        ->and($payment->refresh()->getAttributes())->toBe($paymentBefore)
        ->and($f['invoice']->refresh()->getAttributes())->toBe($invoiceBefore);

    $this->actingAs($f['owner'], 'central')
        ->get(route('owner.billing.index'))
        ->assertOk()
        ->assertSee('Corrigé par avoir émis')
        ->assertSee($credit->uuid)
        ->assertSee('Le montant est corrigé par l’avoir émis sur cette facture.')
        ->assertSee($credit->number);
});

it('C-159 requires the existing reconcile permission to decide a correction report', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment(
        $f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true],
    );
    $this->actingAs($f['owner'], 'central')
        ->post(route('owner.billing.corrections.store', $payment), [
            'request_id' => (string) Str::uuid(),
            'reason' => 'Cette référence doit être contrôlée par le personnel.',
        ])->assertRedirect(route('owner.billing.index'));
    $report = Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::EVENT)->sole();
    $reader = User::factory()->create();
    $reader->assignRole(Role::createWithPermissions('payment-reader-'.Str::random(8), ['saas.payments.view' => 7]));

    $this->actingAs($reader, 'central')
        ->get(route('central.payments.corrections.index'))
        ->assertOk()
        ->assertSee('En attente')
        ->assertDontSee('Examiné — aucune correction nécessaire');
    $this->post(route('central.payments.corrections.review', $report), [
        'decision' => 'not-a-review-decision',
    ])->assertForbidden();

    expect(Activity::forSubject($payment)->where('event', OwnerPaymentCorrectionService::REVIEWED_EVENT)->count())->toBe(0);
});
