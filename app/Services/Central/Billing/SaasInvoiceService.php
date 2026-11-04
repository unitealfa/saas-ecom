<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasInvoiceLine;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaasInvoiceService
{
    private const string EVENT_CREATED = 'saas_invoice_prepared';

    private const string EVENT_INSTALLMENT = 'subscription_installment_created';

    private const string BASE_CHARGE = 'base-period';

    private const array CUSTOMER_FIELDS = ['uuid', 'first_name', 'last_name', 'email', 'legal_form',
        'activity_nature', 'nif', 'nis', 'registration_number', 'artisan_card_number', 'legal_address'];

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'billing_rule_uuid' => ['required', 'uuid:4'],
            'expected_state' => ['required', 'string', 'regex:'.BillingRule::STATE_PATTERN],
            'issuer_name' => ['required', 'string', 'max:255'],
            'no_tax_reason' => ['required', 'string', 'max:2000'],
            ...array_fill_keys(['amount', 'total_amount', 'net_amount', 'tax_amount', 'number', 'sequence_id',
                'status', 'user_id', 'installment_amount', 'operation_key', 'document_type'], ['prohibited']),
        ];
    }

    /** Échéance de base et intention de facture sont créées dans la même transaction.
     * @param  array<string, mixed>  $data
     */
    public function prepareCurrentPeriodWithoutTax(User $actor, Subscription $subscription, array $data): SaasInvoice
    {
        return $this->mutations->run($actor, 'createInstallment', function () use ($subscription): Subscription {
            User::withTrashed()->whereKey($subscription->user_id)->lockForUpdate()->firstOrFail();

            return Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
        }, function (Subscription $subscription) use ($actor, $data): SaasInvoice {
            Gate::forUser($actor)->authorize('create', SaasInvoice::class);
            $values = Validator::make($data, self::rules())->validate();
            $key = $this->periodKey($subscription);
            $existing = SaasInvoice::where('operation_key', 'saas:invoice:'.$key)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->billingRule->uuid !== strtolower($values['billing_rule_uuid'])
                    || $existing->saas_identity_snapshot['name'] !== trim($values['issuer_name'])
                    || ($existing->billing_rule_snapshot['no_tax_reason'] ?? null) !== trim($values['no_tax_reason'])) {
                    $this->fail('conflict', 'Cette période a déjà été préparée avec d’autres données.');
                }

                return $existing;
            }
            if (! hash_equals($subscription->transitionState(), $values['expected_state'])) {
                $this->fail('conflict', 'L’abonnement a changé. Rechargez avant de préparer son échéance.');
            }
            if ($subscription->status !== StatusEnum::ACTIVE || $subscription->period_ends_at === null
                || $subscription->period_starts_at->isFuture() || $subscription->period_ends_at->isPast()
                || $subscription->agreed_amount === '0.00') {
                $this->fail('subscription', 'Choisissez la période en cours d’un abonnement payant actif.');
            }

            $rule = $this->lockRule($values['billing_rule_uuid']);
            $owner = $subscription->owner;
            abort_unless($owner instanceof User, 404);
            $due = now()->startOfSecond()->addDays($rule->parameters['payment_due_days']);
            $installment = SubscriptionInstallment::where('operation_key', 'installment:'.$key)->lockForUpdate()->first();
            if ($installment === null) {
                $installment = new SubscriptionInstallment;
                $installment->forceFill([
                    'user_id' => $owner->id, 'parent_subscription_id' => $subscription->id,
                    'operation_key' => 'installment:'.$key, 'installment_number' => 'ECH-'.Str::uuid(),
                    'installment_amount' => $subscription->agreed_amount, 'due_at' => $due,
                    'installment_status' => InstallmentStatusEnum::PENDING,
                    'period_starts_at' => $subscription->period_starts_at, 'period_ends_at' => $subscription->period_ends_at,
                ])->save();
                activity('central')->causedBy($actor)->performedOn($installment)->event(self::EVENT_INSTALLMENT)
                    ->withProperties(['subscription_uuid' => $subscription->uuid, 'amount' => $installment->installment_amount])
                    ->log('Échéance de la période créée.');
            }
            $invoice = new SaasInvoice;
            $invoice->forceFill([
                'user_id' => $owner->id, 'subscription_id' => $subscription->id, 'installment_id' => $installment->id,
                'billing_rule_id' => $rule->id, 'document_type' => DocumentTypeEnum::INVOICE,
                'operation_key' => 'saas:invoice:'.$key, 'status' => DocumentStatusEnum::DRAFT,
                'billing_rule_snapshot' => [...$rule->only(['uuid', 'code', 'version', 'trigger_event', 'numbering_scope', 'parameters',
                    'validation_reference', 'validated_at', 'effective_at', 'ends_at']),
                    'no_tax_reason' => trim($values['no_tax_reason'])],
                'net_amount' => $installment->installment_amount, 'taxes' => [], 'tax_amount' => '0.00',
                'total_amount' => $installment->installment_amount, 'currency' => SaasInvoice::CURRENCY,
                'period_starts_at' => $installment->period_starts_at, 'period_ends_at' => $installment->period_ends_at,
                'due_at' => $installment->due_at,
                'saas_identity_snapshot' => ['name' => trim($values['issuer_name']), 'preparation_only' => true],
                'customer_identity_snapshot' => $owner->only(self::CUSTOMER_FIELDS),
                'correlation_id' => (string) Str::uuid(),
            ])->save();
            $line = new SaasInvoiceLine;
            $line->forceFill([
                'document_id' => $invoice->id, 'user_id' => $owner->id, 'document_type' => DocumentTypeEnum::INVOICE,
                'line_number' => 1, 'description' => 'Abonnement '.$subscription->plan->name,
                'quantity' => '1.00', 'net_unit_price' => $installment->installment_amount,
                'net_discount' => '0.00', 'net_amount' => $installment->installment_amount,
                'taxes' => [], 'tax_amount' => '0.00', 'total_amount' => $installment->installment_amount,
                'operation_key' => 'saas:line:'.$invoice->uuid.':base', 'correlation_id' => $invoice->getAttribute('correlation_id'),
            ])->save();
            activity('central')->causedBy($actor)->performedOn($invoice)->event(self::EVENT_CREATED)
                ->withProperties(['installment_uuid' => $installment->uuid, 'rule_uuid' => $rule->uuid,
                    'rule_version' => $rule->version, 'amount' => $invoice->total_amount, 'currency' => $invoice->currency])
                ->log('Intention de facture sans taxe préparée, sans émission.');

            return $invoice->refresh();
        });
    }

    private function periodKey(Subscription $subscription): string
    {
        return self::BASE_CHARGE.':'.$subscription->uuid.':'.hash('sha256', json_encode([
            $subscription->period_starts_at->toISOString(), $subscription->period_ends_at?->toISOString(),
        ], JSON_THROW_ON_ERROR));
    }

    private function lockRule(string $uuid): BillingRule
    {
        $candidate = BillingRule::where('uuid', strtolower($uuid))->firstOrFail();
        BillingRule::where('code', $candidate->code)->orderBy('version')->lockForUpdate()->firstOrFail();
        $rule = BillingRule::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
        if (! $rule->isApplicableAt(now())
            || $rule->parameters['document_type'] !== DocumentTypeEnum::INVOICE->value) {
            $this->fail('billing_rule_uuid', 'Choisissez une règle validée actuellement applicable aux factures d’abonnement.');
        }

        return $rule;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
