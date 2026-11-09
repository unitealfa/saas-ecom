<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\PolicyStatusEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\PaymentChoiceEnum;
use App\Enums\Central\Subscriptions\RequestStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Billing\InvoicePaymentService;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasInvoiceService;
use App\Services\Central\Billing\SaasPaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionRequestService
{
    public const int PER_PAGE = 20;

    public const string CREATED = 'subscription_request_created';

    public const string INVOICE_READY = 'subscription_request_invoice_ready';

    public const string INVOICE_BLOCKED = 'subscription_request_invoice_blocked';

    public const string UPDATED = 'subscription_request_updated';

    private const string INVOICE_UNAVAILABLE = 'La facture ne peut pas encore être émise. L’administration doit vérifier les informations fiscales validées, la règle applicable et le stockage privé. Aucun paiement ni droit payant n’a été confirmé.';

    public function __construct(
        private readonly AuthorizationMutation $mutations,
        private readonly SubscriptionPurchaseService $purchases,
        private readonly SaasInvoiceService $invoices,
        private readonly SaasInvoiceIssuanceService $issuance,
        private readonly SaasPaymentService $payments,
        private readonly InvoicePaymentService $balances,
    ) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SubscriptionRequest>
     */
    public function search(User $actor, array $filters, bool $own = false): LengthAwarePaginator
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize($own ? 'create' : 'viewAny', SubscriptionRequest::class);
        $search = trim($filters['search'] ?? '');

        return SubscriptionRequest::with(['owner', 'subscription.plan', 'invoice', 'payment'])
            ->when($own, fn (Builder $query) => $query->where('user_id', $actor->id))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('uuid', $search)
                ->orWhereRelation('invoice', 'number', 'like', '%'.addcslashes($search, '\\%_').'%')
                ->orWhereRelation('owner', 'email', 'like', '%'.addcslashes($search, '\\%_').'%')))
            ->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [...SubscriptionPurchaseService::rules(), 'payment_choice' => ['required', 'integer', Rule::enum(PaymentChoiceEnum::class)],
            ...array_fill_keys(['current_subscription_id', 'current_invoice_id', 'current_payment_id', 'reference_version',
                'decided_by_id', 'decided_at', 'decision_reason', 'invoice_error', 'currency', 'amount'], ['missing'])];
    }

    /** @param array<string, mixed> $data */
    public function create(User $owner, array $data): SubscriptionRequest
    {
        return $this->createFor($owner, $owner, $data);
    }

    /** @param array<string, mixed> $data */
    public function createFor(User $actor, User $owner, array $data): SubscriptionRequest
    {
        abort_if(tenancy()->initialized, 403);
        $request = $this->mutations->run($actor, 'createFor', fn () => [SubscriptionRequest::class, $owner], function () use ($actor, $owner, $data): SubscriptionRequest {
            User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $values = Validator::make($data, self::rules())->validate();
            $key = 'subscription-request:'.$owner->uuid.':'.strtolower($values['operation_id']);
            $payload = hash('sha256', json_encode([strtolower($values['plan_uuid']), (int) $values['period'],
                $values['expected_amount'], (int) $values['payment_choice']], JSON_THROW_ON_ERROR));
            $existing = SubscriptionRequest::where('operation_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->getAttribute('initial_payload_hash') !== $payload) {
                    $this->fail('Cette opération désigne déjà une autre demande.');
                }

                return $existing;
            }
            $request = new SubscriptionRequest;
            $request->forceFill(['user_id' => $owner->id, 'payment_choice' => (int) $values['payment_choice'],
                'submitted_at' => now(), 'operation_key' => $key, 'initial_payload_hash' => $payload])->save();
            $subscription = $this->purchases->chooseFor($actor, $owner, $values);
            $subscription->forceFill(['subscription_request_id' => $request->id])->save();
            $request->forceFill(['current_subscription_id' => $subscription->id])->save();
            $this->audit($actor, $request, self::CREATED, 'Demande enregistrée, sans confirmation de paiement ni activation.');

            return $request->refresh();
        });
        if ($request->status->isFinal() || $request->current_invoice_id !== null && $request->invoice?->status === DocumentStatusEnum::ISSUED) {
            return $request;
        }
        if (Gate::forUser($actor)->denies('prepareInvoice', $request)) {
            return $request;
        }

        return $this->prepareInvoice($actor, $request, $request->managementState());
    }

    public function prepareInvoice(User $actor, SubscriptionRequest $request, string $expected): SubscriptionRequest
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('prepareInvoice', $request);
        try {
            $request = $this->mutations->run($actor, 'prepareInvoice', fn () => $this->lock($request), function (SubscriptionRequest $current) use ($actor, $expected): SubscriptionRequest {
                $this->assertState($current, $expected);
                if ($current->invoice?->status === DocumentStatusEnum::ISSUED) {
                    return $current;
                }
                $rule = $current->invoice->billingRule ?? $this->billingRule();
                $invoice = $this->invoices->prepareForRequest($actor, $current, $rule->uuid);
                $current->forceFill(['current_invoice_id' => $invoice->id, 'invoice_error' => null,
                    'reference_version' => $current->reference_version + 1])->save();

                return $current->refresh();
            });
            $invoice = $request->invoice;
            if ($invoice === null) {
                $this->fail('La facture de cette demande n’a pas été préparée.');
            }
            $invoice = $this->issuance->issueForRequest($actor, $invoice, $invoice->managementState());

            return $this->mutations->run($actor, 'prepareInvoice', fn () => $this->lock($request), function (SubscriptionRequest $current) use ($actor, $invoice): SubscriptionRequest {
                if ($current->current_invoice_id !== $invoice->id || $current->current_subscription_id !== $invoice->subscription_id) {
                    $this->fail('La demande a changé pendant la préparation de sa facture. Rechargez.');
                }
                if ($current->status === RequestStatusEnum::INVOICE_PENDING) {
                    $current->forceFill(['status' => RequestStatusEnum::AWAITING_PAYMENT, 'invoice_error' => null,
                        'reference_version' => $current->reference_version + 1])->save();
                    $this->audit($actor, $current, self::INVOICE_READY, 'Facture émise et disponible avant paiement. Aucun reçu ni droit payant créé.');
                }

                return $current->refresh();
            });
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['conflict'])) {
                throw $exception;
            }

            return $this->mutations->run($actor, 'prepareInvoice', fn () => $this->lock($request), function (SubscriptionRequest $current) use ($actor, $request): SubscriptionRequest {
                if ($current->current_invoice_id !== $request->current_invoice_id) {
                    throw ValidationException::withMessages(['conflict' => 'La facture courante a changé. Rechargez la demande.']);
                }
                if ($current->invoice?->status === DocumentStatusEnum::ISSUED) {
                    return $current;
                }
                if ($current->getAttribute('invoice_error') !== self::INVOICE_UNAVAILABLE) {
                    $current->forceFill(['invoice_error' => self::INVOICE_UNAVAILABLE, 'reference_version' => $current->reference_version + 1])->save();
                    $this->audit($actor, $current, self::INVOICE_BLOCKED, 'Facturation à reprendre après vérification des prérequis ; demande conservée.');
                }

                return $current->refresh();
            });
        }
    }

    /** @param array<string, mixed> $data */
    public function submitProof(User $owner, SubscriptionRequest $request, string $expected, array $data, UploadedFile $proof): SubscriptionRequest
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($owner)->authorize('update', $request);
        Validator::make([...$data, 'proof' => $proof], [...SaasPaymentService::declarationRules(false),
            'proof' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.PaymentDocumentService::MAX_KB]])->validate();
        $current = $this->mutations->run($owner, 'update', fn () => $this->lock($request), function (SubscriptionRequest $current) use ($expected): SubscriptionRequest {
            $this->assertState($current, $expected);
            if ($current->status->isFinal() || $current->payment_choice !== PaymentChoiceEnum::EXTERNAL
                || $current->invoice?->status !== DocumentStatusEnum::ISSUED) {
                $this->fail('Cette demande ne peut pas recevoir un justificatif externe.');
            }

            return $current;
        });
        $this->payments->declareExternalPayment($owner, $current->invoice, [...$data, 'expected_request_state' => $current->managementState()], $proof);

        return $current->refresh();
    }

    /** @param array<string, mixed> $data */
    public function accept(User $actor, SubscriptionRequest $request, string $expected, array $data): SubscriptionRequest
    {
        abort_if(tenancy()->initialized, 403);
        $current = $this->mutations->run($actor, 'accept', fn () => $this->lock($request), function (SubscriptionRequest $current) use ($actor, $expected, $data): SubscriptionRequest {
            abort_if($actor->id === $current->user_id, 403);
            $this->assertState($current, $expected);
            if ($current->status->isFinal() || $current->invoice?->status !== DocumentStatusEnum::ISSUED) {
                $this->fail('Une facture émise et une demande non terminée sont nécessaires.');
            }
            if ($current->payment_choice === PaymentChoiceEnum::EXTERNAL && $current->payment === null) {
                $this->fail('Le commerçant doit fournir son justificatif avant la confirmation du versement externe.');
            }
            if ($current->payment !== null && ! hash_equals($current->payment->managementState(), (string) ($data['expected_payment_state'] ?? ''))) {
                throw ValidationException::withMessages(['conflict' => 'Le paiement ou son justificatif a changé. Rechargez avant de décider.']);
            }

            return $current;
        });
        try {
            if ($current->payment_choice === PaymentChoiceEnum::CASH) {
                if ($current->payment === null) {
                    $this->payments->recordCashPayment($actor, $current->invoice, [...$data, 'currency' => $current->currency,
                        'expected_request_state' => $current->managementState()]);
                } else {
                    $this->payments->resumeCashPayment($actor, $current->payment, $data['expected_payment_state']);
                }
            } else {
                $this->payments->verifyPayment($actor, $current->payment, [...$data, 'expected_state' => $data['expected_payment_state']]);
            }
        } catch (ValidationException $exception) {
            $current->refresh();
            if ($current->status !== RequestStatusEnum::ACCEPTED || $current->payment?->getAttribute('transfer_status') !== TransferStatusEnum::VERIFIED) {
                throw $exception;
            }
        }

        return $current->refresh();
    }

    public function reject(User $actor, SubscriptionRequest $request, string $expected, string $reason, ?string $expectedPayment = null): SubscriptionRequest
    {
        abort_if(tenancy()->initialized, 403);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:2000']])->validate();

        return $this->mutations->run($actor, 'reject', fn () => $this->lock($request), function (SubscriptionRequest $current) use ($actor, $expected, $reason, $expectedPayment): SubscriptionRequest {
            abort_if($actor->id === $current->user_id, 403);
            $this->assertState($current, $expected);
            if ($current->status->isFinal()) {
                $this->fail('Une demande terminée ne peut plus être refusée.');
            }
            if ($current->payment !== null) {
                $payment = SaasPayment::whereKey($current->current_payment_id)->lockForUpdate()->firstOrFail();
                if ($expectedPayment === null || ! hash_equals($payment->managementState(), $expectedPayment)) {
                    $this->fail('Rechargez le paiement avant de prendre une décision.');
                }
                $this->assertCorrectablePayment($payment);
                if ($payment->getAttribute('transfer_status') !== TransferStatusEnum::UNCERTAIN) {
                    $this->payments->rejectPayment($actor, $payment, $expectedPayment, $reason);

                    return $current->refresh();
                }
            }
            $current->forceFill(['status' => RequestStatusEnum::REJECTED, 'decision_reason' => trim($reason),
                'decided_by_id' => $actor->id, 'decided_at' => now(), 'reference_version' => $current->reference_version + 1])->save();
            $this->audit($actor, $current, SubscriptionRequestPaymentState::REJECTED, 'Demande refusée avec motif, sans encaissement ni activation.');

            return $current->refresh();
        });
    }

    public function changePaymentChoice(User $owner, SubscriptionRequest $request, string $expected, PaymentChoiceEnum $choice): SubscriptionRequest
    {
        abort_if(tenancy()->initialized, 403);

        return $this->mutations->run($owner, 'update', fn () => $this->lock($request), function (SubscriptionRequest $current) use ($owner, $expected, $choice): SubscriptionRequest {
            $this->assertState($current, $expected);
            if ($current->status->isFinal()) {
                $this->fail('Une demande terminée est immuable.');
            }
            if ($current->payment_choice === $choice && $current->status !== RequestStatusEnum::REJECTED) {
                return $current;
            }
            $previous = $current->payment_choice;
            $this->retireDeclaration($owner, $current);
            $current->forceFill(['payment_choice' => $choice, 'status' => $current->invoice?->status === DocumentStatusEnum::ISSUED
                ? RequestStatusEnum::AWAITING_PAYMENT : RequestStatusEnum::INVOICE_PENDING,
                'decision_reason' => null, 'decided_by_id' => null, 'decided_at' => null,
                'reference_version' => $current->reference_version + 1])->save();
            activity('central')->causedBy($owner)->performedOn($current)->event(self::UPDATED)
                ->withProperties(['request_uuid' => $current->uuid, 'previous_payment_choice' => $previous->value,
                    'payment_choice' => $choice->value, 'version' => $current->reference_version])
                ->log('Moyen de paiement corrigé ; ancienne déclaration conservée, sans confirmation des fonds.');

            return $current->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function revise(User $owner, SubscriptionRequest $request, string $expected, array $data): SubscriptionRequest
    {
        abort_if(tenancy()->initialized, 403);
        $current = $this->mutations->run($owner, 'update', fn () => $this->lock($request), function (SubscriptionRequest $current) use ($owner, $expected, $data): SubscriptionRequest {
            $this->assertState($current, $expected);
            $values = Validator::make($data, self::rules())->validate();
            if ($current->status->isFinal()) {
                $this->fail('Une demande terminée est immuable.');
            }
            $subscription = $current->subscription()->lockForUpdate()->firstOrFail();
            if ($subscription->plan->uuid === strtolower($values['plan_uuid']) && $subscription->period->value === (int) $values['period']) {
                if ($subscription->agreed_amount !== $values['expected_amount']) {
                    $this->fail('Le montant transmis ne correspond pas à cette période.');
                }

                return $this->changePaymentChoice($owner, $current, $expected, PaymentChoiceEnum::from((int) $values['payment_choice']));
            }
            $this->assertReplaceable($owner, $current);
            $before = ['subscription_uuid' => $subscription->uuid, 'plan_uuid' => $subscription->plan->uuid,
                'amount' => $subscription->agreed_amount, 'period' => $subscription->period->value,
                'invoice_uuid' => $current->invoice?->uuid, 'payment_choice' => $current->payment_choice->value];
            $this->retireDeclaration($owner, $current);
            $this->cancelReservation($subscription);
            $replacement = $this->purchases->choose($owner, $values);
            if ($replacement->status !== StatusEnum::SCHEDULED || ($replacement->getAttribute('subscription_request_id') !== null
                && $replacement->getAttribute('subscription_request_id') !== $current->id)) {
                $this->fail('Cette opération appartient déjà à une autre période. Rechargez la demande.');
            }
            $replacement->forceFill(['subscription_request_id' => $current->id])->save();
            $current->forceFill(['current_subscription_id' => $replacement->id, 'current_invoice_id' => null, 'current_payment_id' => null,
                'payment_choice' => (int) $values['payment_choice'], 'status' => RequestStatusEnum::INVOICE_PENDING,
                'decision_reason' => null, 'decided_by_id' => null, 'decided_at' => null, 'invoice_error' => null,
                'reference_version' => $current->reference_version + 1])->save();
            activity('central')->causedBy($owner)->performedOn($current)->event(self::UPDATED)
                ->withProperties(['before' => $before, 'after' => ['subscription_uuid' => $replacement->uuid,
                    'plan_uuid' => $replacement->plan->uuid, 'amount' => $replacement->agreed_amount,
                    'period' => $replacement->period->value, 'payment_choice' => $current->payment_choice->value]])
                ->log('Offre ou période corrigée après contrôle documentaire ; versions précédentes conservées.');

            return $current->refresh();
        });

        return $current->invoice?->status === DocumentStatusEnum::ISSUED ? $current : $this->prepareInvoice($owner, $current, $current->managementState());
    }

    public function cancel(User $owner, SubscriptionRequest $request, string $expected): SubscriptionRequest
    {
        abort_if(tenancy()->initialized, 403);

        return $this->mutations->run($owner, 'cancel', fn () => $this->lock($request), function (SubscriptionRequest $current) use ($owner, $expected): SubscriptionRequest {
            $this->assertState($current, $expected);
            if ($current->status->isFinal()) {
                $this->fail('Une demande terminée est immuable.');
            }
            $this->assertReplaceable($owner, $current);
            $this->retireDeclaration($owner, $current);
            $this->cancelReservation($current->subscription()->lockForUpdate()->firstOrFail());
            $current->forceFill(['status' => RequestStatusEnum::CANCELLED, 'reference_version' => $current->reference_version + 1])->save();
            $this->audit($owner, $current, self::UPDATED, 'Demande annulée sans paiement ; documents et historique conservés, aucun avoir automatique.');

            return $current->refresh();
        });
    }

    private function assertReplaceable(User $owner, SubscriptionRequest $request): void
    {
        if ($request->subscription?->status !== StatusEnum::SCHEDULED) {
            $this->fail('Seule une période réservée non activée peut être remplacée ou annulée.');
        }
        if ($request->current_invoice_id === null) {
            return;
        }
        foreach (SaasPayment::withoutGlobalScope('payment')->where('document_id', $request->current_invoice_id)->orderBy('id')->lockForUpdate()->get() as $payment) {
            $this->assertCorrectablePayment($payment);
        }
        if ($request->invoice?->status === DocumentStatusEnum::ISSUED && $this->balances->balance($owner, $request->invoice)['net_due'] !== '0.00') {
            $this->fail('Une correction documentaire manuelle complète de la facture doit d’abord être émise par un administrateur autorisé. Aucun avoir ne sera créé automatiquement.');
        }
    }

    private function cancelReservation(Subscription $subscription): void
    {
        $subscription->forceFill(['status' => StatusEnum::CANCELLED, 'ended_at' => now()])->save();
        foreach (SubscriptionInstallment::where('subscription_id', $subscription->id)->orderBy('id')->lockForUpdate()->get() as $due) {
            $due->forceFill(['installment_status' => InstallmentStatusEnum::CANCELLED])->save();
        }
    }

    private function retireDeclaration(User $actor, SubscriptionRequest $request): void
    {
        if ($request->current_payment_id === null) {
            return;
        }
        $payment = SaasPayment::whereKey($request->current_payment_id)->lockForUpdate()->firstOrFail();
        $this->assertCorrectablePayment($payment);
        if ($payment->getAttribute('transfer_status') === TransferStatusEnum::DECLARED) {
            $payment->forceFill(['transfer_status' => TransferStatusEnum::CANCELLED, 'reason' => 'Déclaration remplacée par une correction du commerçant.'])->save();
        }
        activity('central')->causedBy($actor)->performedOn($request)->event(SubscriptionRequestPaymentState::SUPERSEDED)
            ->withProperties(['request_uuid' => $request->uuid, 'payment_uuid' => $payment->getAttribute('uuid'),
                'payment_status' => $payment->getAttribute('transfer_status')->value])
            ->log('Déclaration sans réception de fonds remplacée ; anomalie et justificatifs historiques conservés.');
        $request->forceFill(['current_payment_id' => null, 'reference_version' => $request->reference_version + 1])->save();
    }

    private function assertCorrectablePayment(SaasPayment $payment): void
    {
        if ($payment->getAttribute('record_type') !== SaasPayment::RECORD_TYPE || $payment->hasReportedFunds()
            || ! in_array($payment->getAttribute('transfer_status'), [TransferStatusEnum::DECLARED, TransferStatusEnum::REJECTED, TransferStatusEnum::CANCELLED, TransferStatusEnum::UNCERTAIN], true)) {
            $this->fail('Un encaissement signalé ou une opération financière non résolue doit être rapproché. Aucune somme ne sera effacée par une correction commerciale.');
        }
    }

    private function billingRule(): BillingRule
    {
        $rules = BillingRule::where('policy_status', PolicyStatusEnum::ACTIVE)->where('trigger_event', TriggerEventEnum::INSTALLMENT_DUE)
            ->where('parameters->document_type', DocumentTypeEnum::INVOICE->value)
            ->when(filled(config('central.billing.request_rule_code')), fn ($query) => $query->where('code', config('central.billing.request_rule_code')))
            ->orderBy('id')->get()->filter(fn (BillingRule $rule): bool => $rule->isActiveAt(now()));
        if ($rules->count() !== 1) {
            $this->fail('Une seule règle de facture applicable doit être sélectionnée côté serveur.');
        }

        return $rules->sole();
    }

    private function lock(SubscriptionRequest $request): SubscriptionRequest
    {
        User::whereKey($request->user_id)->lockForUpdate()->firstOrFail();

        return SubscriptionRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
    }

    private function assertState(SubscriptionRequest $request, string $expected): void
    {
        if (! hash_equals($request->managementState(), $expected)) {
            throw ValidationException::withMessages(['conflict' => 'La demande a changé. Rechargez sa dernière version avant de poursuivre.']);
        }
    }

    private function audit(User $actor, SubscriptionRequest $request, string $event, string $message): void
    {
        activity('central')->causedBy($actor)->performedOn($request)->event($event)
            ->withProperties(['request_uuid' => $request->uuid, 'version' => $request->reference_version,
                'subscription_uuid' => $request->subscription?->uuid, 'invoice_uuid' => $request->invoice?->uuid,
                'payment_choice' => $request->payment_choice->value, 'reason' => $request->getAttribute('decision_reason')])->log($message);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['request' => $message]);
    }
}
