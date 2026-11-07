<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\TransferMethodEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Subscriptions\PaidSubscriptionActivation;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaasPaymentService
{
    public const string DECLARED = 'saas_payment_declared';

    public const string VERIFIED = 'saas_payment_verified';

    public const string REJECTED = 'saas_payment_rejected';

    public const string ANOMALY = 'saas_payment_anomaly';

    public const string RECONCILED = 'saas_payment_reconciled';

    public const string PROOF_RESUMED = 'saas_payment_proof_resumed';

    private const int PER_PAGE = 20;

    private const string CASH_ACCOUNT = 'cash:central';

    public function __construct(private readonly AuthorizationMutation $mutations,
        private readonly PaymentDocumentService $documents, private readonly PaidSubscriptionActivation $subscriptions,
        private readonly InvoicePaymentService $balances) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SaasPayment>
     */
    public function search(User $actor, array $filters, bool $own = false): LengthAwarePaginator
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize($own ? 'viewOwn' : 'viewAny', SaasPayment::class);
        $search = trim($filters['search'] ?? '');

        return SaasPayment::with(['invoice', 'owner'])
            ->when($own, fn (Builder $query) => $query->where('user_id', $actor->id))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('transfer_status', $filters['status']))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('uuid', $search)->orWhereRelation('invoice', 'number', 'like', '%'.addcslashes($search, '\\%_').'%')))
            ->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
    }

    /** @param array<string, mixed> $data */
    public function reconcilePayment(User $actor, SaasPayment $payment, array $data): SaasPayment
    {
        abort_if(tenancy()->initialized, 403);
        $rules = self::verificationRules();
        unset($rules['received']);
        if ($payment->getAttribute('transfer_status') === TransferStatusEnum::UNCERTAIN) {
            $rules = ['expected_state' => $rules['expected_state'], 'review_note' => ['required', 'string', 'max:2000']];
        }
        $values = Validator::make($data, $rules)->validate();

        return $this->mutations->run($actor, 'reconcile', fn () => $this->lockPayment($payment),
            function (SaasPayment $current) use ($actor, $values): SaasPayment {
                if (! hash_equals($current->managementState(), $values['expected_state'])) {
                    $this->fail('La déclaration a changé. Rechargez avant le rapprochement.');
                }
                if ($current->getAttribute('transfer_status') === TransferStatusEnum::UNCERTAIN) {
                    $details = $current->getAttribute('encrypted_transfer_details') ?? [];
                    $current->forceFill(['encrypted_transfer_details' => [...$details, 'reconciliation_note' => $values['review_note'],
                        'reconciled_by_id' => $actor->id, 'reconciled_at' => now()->toISOString()]])->save();
                    $this->audit($actor, $current, self::RECONCILED, 'Anomalie examinée et note privée conservée, sans activation, crédit ni remboursement automatique.');

                    return $current;
                }
                if ($current->getAttribute('transfer_status') !== TransferStatusEnum::DECLARED
                    || $current->getAttribute('transfer_method') === TransferMethodEnum::CASH_IN_PERSON) {
                    $this->fail('Seule une déclaration externe inchangée peut recevoir ce rapprochement. Les encaissements vérifiés restent immuables.');
                }
                $this->documents->assertProof($current);
                $account = $this->normalizeReference($values['account']);
                $details = $current->getAttribute('encrypted_transfer_details') ?? [];
                $current->forceFill(['transfer_reference' => $this->normalizeReference($values['reference']),
                    'financial_account_key' => hash('sha256', $current->getAttribute('transfer_method')->network().':'.$account),
                    'encrypted_transfer_details' => [...$details, 'account' => $account],
                    'occurred_at' => CarbonImmutable::parse($values['occurred_at'])])->save();
                $this->audit($actor, $current, self::RECONCILED, 'Rapprochement renseigné sans confirmation de réception des fonds ni activation.');

                return $current;
            });
    }

    /** @return array<string, mixed> */
    public static function declarationRules(bool $cash): array
    {
        return ['amount' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D', 'gt:0'],
            'currency' => ['required', Rule::in([SaasInvoice::CURRENCY])], 'operation_id' => ['required', 'uuid:4'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            ...($cash ? ['received' => ['required', 'accepted']] : [
                'method' => ['required', 'integer', Rule::in([TransferMethodEnum::BANK_TRANSFER->value, TransferMethodEnum::CCP_TRANSFER->value, TransferMethodEnum::BARIDIMOB->value])], 'reference' => ['nullable', 'string', 'max:255']]),
            ...array_fill_keys(['user_id', 'document_id', 'transfer_status', 'validated_by_id', 'validated_at', 'proof_media_id',
                'source_proof_media_id', 'record_type', 'original_payment_id', 'credit_note_id', 'refund_reason', 'reversal_of_id',
                'transaction_fingerprint', 'financial_account_key', 'operation_key', 'encrypted_transfer_details'], ['missing'])];
    }

    /** @return array<string, mixed> */
    public static function verificationRules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:'.SaasPayment::STATE_PATTERN], 'received' => ['required', 'accepted'],
            'account' => ['required', 'string', 'max:255'], 'reference' => ['required', 'string', 'max:255'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now']];
    }

    /** @param array<string, mixed> $data */
    public function recordCashPayment(User $actor, SaasInvoice $invoice, array $data): SaasPayment
    {
        $payment = $this->declare($actor, $invoice, $data, null);
        $this->documents->prepareInternalProof($actor, $payment);
        if ($payment->getAttribute('transfer_status') === TransferStatusEnum::DECLARED) {
            return $this->verifyPayment($actor, $payment, ['expected_state' => $payment->managementState(), 'received' => true,
                'account' => self::CASH_ACCOUNT, 'reference' => $payment->getAttribute('transfer_reference'),
                'occurred_at' => $payment->getAttribute('occurred_at')->toDateTimeString()]);
        }
        if ($payment->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED) {
            $this->documents->generatePaymentReceipt($actor, $payment);
        }

        return $payment;
    }

    /** @param array<string, mixed> $data */
    public function declareExternalPayment(User $actor, SaasInvoice $invoice, array $data, UploadedFile $proof): SaasPayment
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('declarePayment', $invoice);
        $prepared = $this->documents->prepareExternalProof($proof);
        $payment = $this->declare($actor, $invoice, $data, $proof);
        $this->documents->attachExternalProof($actor, $payment, $prepared);

        return $payment;
    }

    public function resumeCashPayment(User $actor, SaasPayment $payment, string $expected): SaasPayment
    {
        abort_if(tenancy()->initialized, 403);
        $current = $this->mutations->run($actor, 'verify', fn () => $this->lockPayment($payment), function (SaasPayment $current) use ($expected): SaasPayment {
            if ($current->getAttribute('transfer_method') !== TransferMethodEnum::CASH_IN_PERSON
                || ($current->getAttribute('encrypted_transfer_details')['cash_received'] ?? false) !== true) {
                $this->fail('Cette reprise concerne uniquement un encaissement espèces déjà déclaré reçu.');
            }
            if (! hash_equals($current->managementState(), $expected)) {
                $this->fail('L’encaissement a changé. Rechargez avant de reprendre.');
            }

            return $current;
        });
        $this->documents->prepareInternalProof($actor, $current);
        if ($current->getAttribute('transfer_status') !== TransferStatusEnum::DECLARED) {
            return $current;
        }

        return $this->verifyPayment($actor, $current, ['expected_state' => $current->managementState(), 'received' => true,
            'account' => self::CASH_ACCOUNT, 'reference' => $current->getAttribute('transfer_reference'),
            'occurred_at' => $current->getAttribute('occurred_at')->toDateTimeString()]);
    }

    public function resumeExternalProof(User $actor, SaasPayment $payment, string $expected, UploadedFile $file): SaasPayment
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('supplyProof', $payment);
        $prepared = $this->documents->prepareExternalProof($file);
        $current = $this->mutations->run($actor, 'supplyProof', fn () => $this->lockPayment($payment),
            function (SaasPayment $current) use ($actor, $expected, $file): SaasPayment {
                if (! hash_equals($current->managementState(), $expected)) {
                    $this->fail('La déclaration a changé. Rechargez avant de compléter son justificatif.');
                }
                $details = $current->getAttribute('encrypted_transfer_details') ?? [];
                $payload = hash('sha256', json_encode([$current->invoice->uuid, $current->getAttribute('amount'), $current->getAttribute('currency'),
                    $current->getAttribute('transfer_method')->value, $current->getAttribute('occurred_at')->toISOString(), hash('sha256', $file->getContent()),
                    $current->getAttribute('transfer_reference')], JSON_THROW_ON_ERROR));
                $current->forceFill(['encrypted_transfer_details' => [...$details, 'previous_payload' => $details['previous_payload'] ?? $details['payload'] ?? null,
                    'payload' => $payload]])->save();
                $this->audit($actor, $current, self::PROOF_RESUMED, 'Complément du justificatif demandé sur la même déclaration, sans confirmation de paiement.');

                return $current;
            });
        $this->documents->attachExternalProof($actor, $current, $prepared);

        return $current;
    }

    /** @param array<string, mixed> $data */
    private function declare(User $actor, SaasInvoice $invoice, array $data, ?UploadedFile $proof): SaasPayment
    {
        abort_if(tenancy()->initialized, 403);
        $cash = $proof === null;

        return $this->mutations->run($actor, $cash ? 'recordCashPayment' : 'declarePayment', fn () => $this->lockInvoice($invoice),
            function (SaasInvoice $current) use ($actor, $data, $proof, $cash): SaasPayment {
                $values = Validator::make($data, self::declarationRules($cash))->validate();
                if ($current->status !== DocumentStatusEnum::ISSUED) {
                    $this->fail('Seule une facture émise peut recevoir une déclaration de paiement.');
                }
                $amount = (string) BigDecimal::of($values['amount'])->toScale(2);
                $method = $cash ? TransferMethodEnum::CASH_IN_PERSON : TransferMethodEnum::from((int) $values['method']);
                $date = CarbonImmutable::parse($values['occurred_at'])->startOfSecond();
                $payload = hash('sha256', json_encode([$current->uuid, $amount, $values['currency'], $method->value, $date->toISOString(),
                    $cash ? true : hash('sha256', $proof->getContent()), $values['reference'] ?? null], JSON_THROW_ON_ERROR));
                $key = 'saas:payment:'.$current->owner->uuid.':'.strtolower($values['operation_id']);
                $replay = SaasPayment::where('operation_key', $key)->lockForUpdate()->first();
                if ($replay !== null) {
                    if ($replay->getAttribute('document_id') !== $current->id
                        || ($replay->getAttribute('encrypted_transfer_details')['payload'] ?? null) !== $payload) {
                        $this->fail('Cette demande a déjà été utilisée avec d’autres données.');
                    }

                    return $replay;
                }
                $payments = SaasPayment::where('document_id', $current->id)->orderBy('id')->lockForUpdate()->get();
                foreach ($payments as $previous) {
                    if (! $cash && ($previous->getAttribute('encrypted_transfer_details')['payload'] ?? null) === $payload) {
                        return $previous;
                    }
                }
                $netDue = $this->balances->balance($actor, $current)['net_due'];
                $anomaly = $amount !== $netDue || $payments->contains(fn (SaasPayment $p): bool => $p->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED);
                $payment = new SaasPayment;
                $uuid = (string) Str::uuid();
                $payment->forceFill(['uuid' => $uuid, 'user_id' => $current->user_id, 'document_id' => $current->id,
                    'record_type' => SaasPayment::RECORD_TYPE, 'transfer_method' => $method,
                    'transfer_status' => $anomaly ? TransferStatusEnum::UNCERTAIN : TransferStatusEnum::DECLARED,
                    'amount' => $amount, 'currency' => $values['currency'], 'occurred_at' => $date,
                    'created_by_id' => $actor->id, 'operation_key' => $key, 'correlation_id' => (string) Str::uuid(),
                    'transfer_reference' => $cash ? 'CASH-'.$uuid : ($values['reference'] ?? null),
                    'encrypted_transfer_details' => ['payload' => $payload, 'cash_received' => $cash, 'payment_required' => true],
                    'reason' => $anomaly ? 'Montant ou obligation à rapprocher ; aucun droit supplémentaire accordé.' : null])->save();
                $this->audit($actor, $payment, $anomaly ? self::ANOMALY : self::DECLARED,
                    $cash ? 'Espèces déclarées reçues par l’administration ; contrôle du règlement intégral.' : 'Justificatif externe déclaré, sans confirmation des fonds.');

                return $payment;
            });
    }

    /** @param array<string, mixed> $data */
    public function verifyPayment(User $actor, SaasPayment $payment, array $data): SaasPayment
    {
        abort_if(tenancy()->initialized, 403);
        $values = Validator::make($data, self::verificationRules())->validate();
        try {
            $result = $this->mutations->run($actor, 'verify', fn () => $this->lockPayment($payment),
                function (SaasPayment $current) use ($actor, $values): SaasPayment {
                    abort_if($actor->id === $current->getAttribute('user_id'), 403);
                    $reference = $this->normalizeReference($values['reference']);
                    $account = $this->normalizeReference($values['account']);
                    $accountKey = $current->getAttribute('transfer_method') === TransferMethodEnum::CASH_IN_PERSON
                        ? self::CASH_ACCOUNT : hash('sha256', $current->getAttribute('transfer_method')->network().':'.$account);
                    $fingerprint = hash('sha256', $current->getAttribute('transfer_method')->network().':'.$accountKey.':received:'.$reference);
                    if ($current->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED) {
                        if ($current->getAttribute('transaction_fingerprint') !== $fingerprint) {
                            $this->fail('Cet encaissement est déjà confirmé avec une autre référence.');
                        }

                        return $current;
                    }
                    if ($current->getAttribute('transfer_status') !== TransferStatusEnum::DECLARED) {
                        $this->fail('Cette déclaration refusée ou en anomalie ne peut pas être confirmée comme règlement normal.');
                    }
                    if (! hash_equals($current->managementState(), $values['expected_state'])) {
                        $this->fail('La déclaration a changé. Rechargez avant de prendre une décision.');
                    }
                    $invoice = $current->invoice;
                    $due = $invoice->installment;
                    if ($invoice->status !== DocumentStatusEnum::ISSUED
                        || $current->getAttribute('currency') !== $invoice->currency) {
                        $this->fail('Cette obligation exige un seul paiement intégral non encore confirmé, dans la bonne devise.');
                    }
                    if ($current->getAttribute('transfer_method') === TransferMethodEnum::CASH_IN_PERSON
                        && ($reference !== $this->normalizeReference($current->getAttribute('transfer_reference')) || $account !== strtoupper(self::CASH_ACCOUNT))) {
                        $this->fail('Les espèces utilisent leur référence interne et le registre central, sans référence bancaire.');
                    }
                    $this->documents->assertProof($current);
                    $netDue = $this->balances->balance($actor, $invoice)['net_due'];
                    $received = ['occurred_at' => CarbonImmutable::parse($values['occurred_at']),
                        'transfer_reference' => $current->getAttribute('transfer_method') === TransferMethodEnum::CASH_IN_PERSON ? $current->getAttribute('transfer_reference') : $reference,
                        'financial_account_key' => $accountKey,
                        'encrypted_transfer_details' => [...($current->getAttribute('encrypted_transfer_details') ?? []),
                            'account' => $account, 'receipt_reported_by_id' => $actor->id, 'receipt_reported_at' => now()->toISOString(), 'settlement_total' => $netDue]];
                    if ($current->getAttribute('amount') !== $netDue || ! $due->matchesFullPeriod($invoice->subscription) || $due->installment_status !== InstallmentStatusEnum::PENDING
                        || $invoice->subscription->period_ends_at?->lessThanOrEqualTo(now())
                        || $invoice->subscription->status === StatusEnum::CANCELLED
                        || SaasPayment::where('document_id', $invoice->id)->where('transfer_status', TransferStatusEnum::VERIFIED)->lockForUpdate()->first(['id']) !== null) {
                        $current->forceFill([...$received, 'transfer_status' => TransferStatusEnum::UNCERTAIN,
                            'reason' => 'Réception signalée sur une obligation déjà réglée ou non activable. Aucun droit supplémentaire ; rapprochement administratif nécessaire.'])->save();
                        $this->audit($actor, $current, self::ANOMALY, 'Réception supplémentaire ou hors période conservée sans double activation ni remboursement automatique.');

                        return $current->refresh();
                    }
                    $current->forceFill([...$received, 'transfer_status' => TransferStatusEnum::VERIFIED, 'validated_by_id' => $actor->id,
                        'validated_at' => now(), 'transaction_fingerprint' => $fingerprint])->save();
                    $this->subscriptions->activatePaidSubscription($actor, $invoice->subscription, $due);
                    $this->audit($actor, $current, self::VERIFIED, 'Réception intégrale des fonds confirmée ; période payée.');

                    return $current->refresh();
                });
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'active_transaction_fingerprint')) {
                throw $exception;
            }
            $this->fail('Cette réception de fonds est déjà associée à un paiement vérifié. Aucun second règlement enregistré.');
        }
        if ($result->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED) {
            $this->documents->generatePaymentReceipt($actor, $result);
        }

        return $result;
    }

    public function rejectPayment(User $actor, SaasPayment $payment, string $expected, string $reason): SaasPayment
    {
        abort_if(tenancy()->initialized, 403);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:2000']])->validate();

        return $this->mutations->run($actor, 'reject', fn () => $this->lockPayment($payment),
            function (SaasPayment $current) use ($actor, $expected, $reason): SaasPayment {
                if ($current->getAttribute('transfer_status') !== TransferStatusEnum::DECLARED) {
                    $this->fail('Seule une déclaration non vérifiée peut être refusée. Un encaissement vérifié reste immuable.');
                }
                if (! hash_equals($current->managementState(), $expected)) {
                    $this->fail('La déclaration a changé. Rechargez avant de la refuser.');
                }
                if ($current->getAttribute('transfer_method') === TransferMethodEnum::CASH_IN_PERSON) {
                    $this->fail('Des espèces déclarées reçues ne peuvent pas être effacées par un refus. Un rapprochement administratif est nécessaire.');
                }
                $current->forceFill(['transfer_status' => TransferStatusEnum::REJECTED, 'reason' => trim($reason),
                    'validated_by_id' => $actor->id, 'validated_at' => now()])->save();
                $this->audit($actor, $current, self::REJECTED, 'Déclaration refusée avec motif conservé sur l’opération.');

                return $current;
            });
    }

    private function lockInvoice(SaasInvoice $invoice): SaasInvoice
    {
        $owner = User::withTrashed()->whereKey($invoice->user_id)->lockForUpdate()->firstOrFail();
        $subscriptions = Subscription::where('user_id', $invoice->user_id)->orderBy('id')->lockForUpdate()->get();
        $due = SubscriptionInstallment::whereKey($invoice->installment_id)->lockForUpdate()->firstOrFail();
        $current = SaasInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        $subscription = $subscriptions->firstWhere('id', $current->subscription_id);
        if ($subscription === null || $due->parent_subscription_id !== $subscription->id || $due->user_id !== $owner->id) {
            $this->fail('La période et son propriétaire ne correspondent plus à la facture.');
        }
        // Réutiliser les lectures verrouillées, sans relire un ancien snapshot MySQL.
        $subscription->setRelation('owner', $owner);

        return $current->setRelation('owner', $owner)->setRelation('subscription', $subscription)->setRelation('installment', $due);
    }

    private function lockPayment(SaasPayment $payment): SaasPayment
    {
        $original = SaasPayment::whereKey($payment->getKey())->firstOrFail();
        $invoice = $this->lockInvoice($original->invoice);
        $current = SaasPayment::whereKey($original->getKey())->lockForUpdate()->firstOrFail();
        if ($current->getAttribute('document_id') !== $invoice->id) {
            $this->fail('La facture liée au paiement a changé. Rechargez.');
        }
        $current->setRelation('invoice', $invoice);

        return $current;
    }

    private function normalizeReference(string $value): string
    {
        $normalized = preg_replace('/[\s-]+/u', '', mb_strtoupper(trim($value)));
        if ($normalized === null || $normalized === '') {
            $this->fail('Le compte et la référence réels doivent être renseignés.');
        }

        return $normalized;
    }

    private function audit(User $actor, SaasPayment $payment, string $event, string $message): void
    {
        activity('central')->causedBy($actor)->performedOn($payment)->event($event)
            ->tap(fn ($activity) => $activity->forceFill(['correlation_id' => $payment->getAttribute('correlation_id')]))
            ->withProperties(['payment_uuid' => $payment->getAttribute('uuid'), 'amount' => $payment->getAttribute('amount'), 'currency' => $payment->getAttribute('currency')])
            ->log($message);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payment' => $message]);
    }
}
