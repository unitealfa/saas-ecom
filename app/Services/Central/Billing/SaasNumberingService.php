<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\BillingSequence;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaasNumberingService
{
    private const string EVENT_RESERVED = 'saas_invoice_number_reserved';

    private const string EVENT_CREDIT_RESERVED = 'saas_credit_note_number_reserved';

    private const string EVENT_INITIALIZED = 'saas_sequence_initialized';

    private const array AMOUNTS = ['net_amount', 'tax_amount', 'total_amount'];

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    public function reserve(User $actor, SaasInvoice $invoice, string $expected): SaasInvoice
    {
        return $this->reserveNextNumber($actor, $invoice, $expected, $this->assertPrepared(...));
    }

    /**
     * Le module appelant valide ses règles métier sous les mêmes verrous, avant la réservation.
     *
     * @param  Closure(SaasInvoice): void  $validateDocument
     */
    public function reserveNextNumber(User $actor, SaasInvoice $document, string $expected, Closure $validateDocument): SaasInvoice
    {
        $this->assertCentralContext();

        return $this->mutations->run($actor, 'reserveNumber', function () use ($document): SaasInvoice {
            User::withTrashed()->whereKey($document->user_id)->lockForUpdate()->firstOrFail();
            Subscription::whereKey($document->subscription_id)->lockForUpdate()->firstOrFail();
            SubscriptionInstallment::whereKey($document->installment_id)->lockForUpdate()->firstOrFail();

            return $document->newQuery()->whereKey($document->id)->lockForUpdate()->firstOrFail();
        }, function (SaasInvoice $invoice) use ($actor, $expected, $validateDocument): SaasInvoice {
            if ($invoice->status === DocumentStatusEnum::PREPARING) {
                return $invoice;
            }
            if (! hash_equals($invoice->managementState(), $expected)) {
                $this->fail('conflict', 'La facture a changé. Rechargez avant de réserver son numéro.');
            }
            if ($invoice->document_type === DocumentTypeEnum::INVOICE) {
                $this->assertInvoiceBilling($invoice);
            }
            $validateDocument($invoice);
            $year = (int) now()->format('Y');
            $sequence = $this->lockSequence($actor, $invoice->document_type, $year);
            $next = $sequence->next_number;
            if ($next <= 0 || $next === PHP_INT_MAX) {
                $this->fail('sequence', 'Le compteur ne permet pas de réserver un nouveau numéro.');
            }
            $sequence->forceFill(['next_number' => $next + 1])->save();
            $invoice->forceFill([
                'sequence_id' => $sequence->id, 'fiscal_year' => $year, 'sequence_number' => $next,
                'number' => sprintf('%s-%d-%06d', $sequence->prefix, $year, $next),
                'status' => DocumentStatusEnum::PREPARING,
            ])->save();
            $event = $invoice->document_type === DocumentTypeEnum::INVOICE ? self::EVENT_RESERVED : self::EVENT_CREDIT_RESERVED;
            activity('central')->causedBy($actor)->performedOn($invoice)->event($event)
                ->tap(fn (Activity $activity) => $activity->forceFill(['correlation_id' => $invoice->getAttribute('correlation_id')]))
                ->withProperties(['sequence_uuid' => $sequence->uuid, 'number' => $invoice->number,
                    'correlation_id' => $invoice->getAttribute('correlation_id')])
                ->log('Numéro réservé une seule fois ; document non émis.');

            return $invoice->refresh();
        });
    }

    public function initialize(User $actor, DocumentTypeEnum $type, int $year): BillingSequence
    {
        $this->assertCentralContext();

        return $this->mutations->run($actor, 'initialize', fn () => [BillingSequence::class, $type],
            function () use ($actor, $type, $year): BillingSequence {
                Validator::make(['document_type' => $type->value, 'fiscal_year' => $year], self::initializationRules())->validate();

                return $this->lockSequence($actor, $type, $year);
            });
    }

    /** @return array<string, mixed> */
    public static function initializationRules(): array
    {
        return [
            'document_type' => ['required', 'integer', Rule::enum(DocumentTypeEnum::class)],
            'fiscal_year' => ['required', 'integer', 'min:1000', 'max:9999'],
        ];
    }

    private function lockSequence(User $actor, DocumentTypeEnum $type, int $year): BillingSequence
    {
        $sequence = BillingSequence::where('document_type', $type)->where('fiscal_year', $year)->lockForUpdate()->first();
        if ($sequence !== null) {
            return $sequence;
        }
        $sequence = new BillingSequence;
        $sequence->forceFill([
            'document_type' => $type, 'fiscal_year' => $year,
            'prefix' => $type->numberingPrefix(), 'next_number' => 1,
            'operation_key' => 'saas:sequence:'.$type->value.':'.$year,
        ]);
        try {
            $sequence->save();
        } catch (UniqueConstraintViolationException) {
            return BillingSequence::where('document_type', $type)->where('fiscal_year', $year)
                ->lockForUpdate()->firstOrFail();
        }
        activity('central')->causedBy($actor)->performedOn($sequence)->event(self::EVENT_INITIALIZED)
            ->withProperties(['document_type' => $type->value, 'fiscal_year' => $year, 'prefix' => $sequence->prefix])
            ->log('Compteur initialisé sans consommation de numéro.');

        return $sequence;
    }

    private function assertInvoiceBilling(SaasInvoice $invoice): void
    {
        $rule = $invoice->billingRule;
        BillingRule::where('code', $rule->code)->orderBy('version')->lockForUpdate()->firstOrFail();
        $rule = BillingRule::whereKey($rule->id)->lockForUpdate()->firstOrFail();
        Validator::make($rule->only(['code', 'trigger_event', 'parameters']), BillingRuleService::contentRules())->validate();
        if (! $rule->isActiveAt(now())) {
            $this->fail('rule', 'La règle n’est plus active et applicable. Aucune réservation effectuée.');
        }
        $installment = $invoice->installment;
        $periodObligations = SubscriptionInstallment::where('parent_subscription_id', $invoice->subscription_id)
            ->where('period_starts_at', $installment->period_starts_at)->where('period_ends_at', $installment->period_ends_at)
            ->orderBy('id')->lockForUpdate()->get(['id']);
        if ($periodObligations->count() !== 1 || ! $installment->matchesFullPeriod($invoice->subscription)
            || $installment->installment_status !== InstallmentStatusEnum::PENDING
            || $invoice->total_amount !== $installment->installment_amount
            || ! $invoice->period_starts_at->equalTo($installment->period_starts_at)
            || ! $invoice->period_ends_at->equalTo($installment->period_ends_at)) {
            $this->fail('installment', 'Le montant intégral et la période de la facture doivent correspondre à son unique échéance non réglée.');
        }
    }

    private function assertPrepared(SaasInvoice $invoice): void
    {
        if (trim((string) ($invoice->saas_identity_snapshot['name'] ?? '')) === ''
            || trim((string) ($invoice->customer_identity_snapshot['first_name'] ?? '')) === ''
            || trim((string) ($invoice->billing_rule_snapshot['no_tax_reason'] ?? '')) === '') {
            $this->fail('invoice', 'Les informations nécessaires à cette préparation sont incomplètes.');
        }
        $lines = $invoice->lines()->orderBy('id')->lockForUpdate()->get();
        if ($lines->isEmpty()) {
            $this->fail('invoice', 'Une facture doit contenir au moins une ligne.');
        }
        foreach (self::AMOUNTS as $field) {
            $sum = 0;
            foreach ($lines as $line) {
                $amount = (int) str_replace('.', '', (string) $line->getAttribute($field));
                if ($amount < 0 || $sum > PHP_INT_MAX - $amount) {
                    $this->fail('invoice', 'Le montant des lignes est invalide.');
                }
                $sum += $amount;
            }
            if ($sum !== (int) str_replace('.', '', (string) $invoice->getAttribute($field))) {
                $this->fail('invoice', 'Les totaux ne correspondent pas aux lignes de la facture.');
            }
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function assertCentralContext(): void
    {
        if (tenancy()->initialized) {
            throw new AuthorizationException('Cette opération appartient à l’administration centrale.');
        }
    }
}
