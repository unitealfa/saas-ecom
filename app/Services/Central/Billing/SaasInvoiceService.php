<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasInvoiceLine;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaasInvoiceService
{
    private const int PER_PAGE = 20;

    private const string EVENT_CREATED = 'saas_invoice_prepared';

    private const string EVENT_INSTALLMENT = 'subscription_installment_created';

    private const string BASE_CHARGE = 'base-period';

    private const array CUSTOMER_FIELDS = ['uuid', 'first_name', 'last_name', 'email', 'legal_form',
        'activity_nature', 'nif', 'nis', 'registration_number', 'artisan_card_number', 'legal_address'];

    public function __construct(private readonly AuthorizationMutation $mutations, private readonly InvoiceCalculator $calculator) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SaasInvoice>
     */
    public function search(User $actor, array $filters, bool $own = false): LengthAwarePaginator
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize($own ? 'viewOwn' : 'viewAny', SaasInvoice::class);
        $search = trim($filters['search'] ?? '');

        return SaasInvoice::with('owner')
            ->when($own, fn (Builder $query) => $query->where('user_id', $actor->id))
            ->when(! $own && isset($filters['owner']), fn (Builder $query) => $query->whereRelation('owner', 'uuid', $filters['owner']))
            ->when($search !== '', fn (Builder $query) => $query->where('number', 'like', '%'.addcslashes($search, '\\%_').'%'))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['period_start']), fn (Builder $query) => $query->where('period_ends_at', '>', CarbonImmutable::parse($filters['period_start'])))
            ->when(isset($filters['period_end']), fn (Builder $query) => $query->where('period_starts_at', '<', CarbonImmutable::parse($filters['period_end'])))
            ->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
    }

    public function prepareInstallment(User $actor, Subscription $subscription, SubscriptionInstallment $installment, string $ruleUuid, string $expected): SaasInvoice
    {
        return $this->prepare($actor, $subscription, $installment, $ruleUuid, $expected, null);
    }

    public function prepareForRequest(User $actor, SubscriptionRequest $request, string $ruleUuid): SaasInvoice
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('prepareInvoice', $request);
        $subscription = $request->subscription;
        abort_unless($subscription instanceof Subscription, 422);
        $due = SubscriptionInstallment::where('parent_subscription_id', $subscription->id)->sole();

        return $this->prepare($actor, $subscription, $due, $ruleUuid, $due->managementState(), $request);
    }

    private function prepare(User $actor, Subscription $subscription, SubscriptionInstallment $installment, string $ruleUuid, string $expected, ?SubscriptionRequest $request): SaasInvoice
    {
        abort_if(tenancy()->initialized, 403);

        return $this->mutations->run($actor, $request === null ? 'create' : 'prepareInvoice', fn () => $request ?? SaasInvoice::class,
            function () use ($actor, $subscription, $installment, $ruleUuid, $expected, $request): SaasInvoice {
                $owner = User::withTrashed()->whereKey($subscription->user_id)->lockForUpdate()->firstOrFail();
                $parent = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
                $due = SubscriptionInstallment::whereKey($installment->id)->lockForUpdate()->firstOrFail();
                if ($request !== null) {
                    $currentRequest = SubscriptionRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
                    Gate::forUser($actor)->authorize('prepareInvoice', $currentRequest);
                    if ($currentRequest->current_subscription_id !== $parent->id || $parent->getAttribute('subscription_request_id') !== $currentRequest->id || $currentRequest->status->isFinal()) {
                        $this->fail('request', 'La demande a changé. Aucune facture ne peut être créée pour son ancienne version.');
                    }
                }
                if ($due->parent_subscription_id !== $parent->id || $due->user_id !== $owner->id
                    || $due->period_ends_at->lessThanOrEqualTo($due->period_starts_at)) {
                    $this->fail('installment', 'Cette échéance ne peut pas être facturée pour cet abonnement.');
                }
                $existing = SaasInvoice::where('installment_id', $due->id)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ($existing->billingRule->uuid !== strtolower($ruleUuid)) {
                        $this->fail('conflict', 'Cette échéance possède déjà une facture utilisant une autre règle.');
                    }

                    return $existing;
                }
                $this->assertFullPeriod($parent, $due);
                $rule = $this->lockRule($ruleUuid);
                if (! hash_equals($due->managementState(), $expected)) {
                    $this->fail('conflict', 'L’échéance a changé. Rechargez avant de préparer sa facture.');
                }
                [$issuer, $customer, $policy] = $this->fiscalData($owner);
                $amounts = $this->calculator->fromTotal($due->installment_amount, 'Abonnement '.$parent->plan->name, $policy['rates']);
                $invoice = new SaasInvoice;
                $invoice->forceFill([
                    'user_id' => $owner->id, 'subscription_id' => $parent->id, 'installment_id' => $due->id,
                    'billing_rule_id' => $rule->id, 'document_type' => DocumentTypeEnum::INVOICE,
                    'operation_key' => 'saas:invoice:installment:'.$due->uuid, 'status' => DocumentStatusEnum::DRAFT,
                    'billing_rule_snapshot' => [...$rule->only(['uuid', 'code', 'version', 'parameters', 'validation_reference', 'validated_at', 'effective_at', 'ends_at']), 'fiscal_policy' => $policy],
                    'net_amount' => $amounts['net_amount'], 'taxes' => $amounts['taxes'],
                    'tax_amount' => $amounts['tax_amount'], 'total_amount' => $amounts['total_amount'], 'currency' => SaasInvoice::CURRENCY,
                    'period_starts_at' => $due->period_starts_at, 'period_ends_at' => $due->period_ends_at, 'due_at' => $due->due_at,
                    'saas_identity_snapshot' => $issuer, 'customer_identity_snapshot' => $customer, 'correlation_id' => (string) Str::uuid(),
                ])->save();
                foreach ($amounts['lines'] as $index => $line) {
                    (new SaasInvoiceLine)->forceFill([...$line, 'document_id' => $invoice->id, 'user_id' => $owner->id,
                        'document_type' => DocumentTypeEnum::INVOICE, 'line_number' => $index + 1,
                        'operation_key' => 'saas:line:'.$invoice->uuid.':'.($index + 1), 'correlation_id' => $invoice->getAttribute('correlation_id')])->save();
                }
                activity('central')->causedBy($actor)->performedOn($invoice)->event(self::EVENT_CREATED)
                    ->tap(fn ($activity) => $activity->forceFill(['correlation_id' => $invoice->getAttribute('correlation_id')]))
                    ->withProperties(['invoice_uuid' => $invoice->uuid, 'installment_uuid' => $due->uuid, 'amount' => $invoice->total_amount])
                    ->log('Facture préparée depuis une échéance, sans émission.');

                return $invoice->refresh();
            });
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>} */
    private function fiscalData(User $owner): array
    {
        $issuer = config('central.billing.issuer', []);
        $policy = config('central.billing.tax_policy', []);
        $customer = config('central.billing.customers', [])[$owner->uuid] ?? [];
        $customer['email'] = $owner->email;
        $proof = ['name' => ['required', 'string', 'max:255'], 'address' => ['required', 'string', 'max:2000'],
            'nif' => ['required', 'string', 'max:255'], 'validation_reference' => ['required', 'string', 'max:255'],
            'validated_at' => ['required', 'date', 'before_or_equal:now']];
        Validator::make($issuer, $proof, ['required' => 'Le champ :attribute de l’émetteur fiscal est obligatoire.'])->validate();
        Validator::make($customer, [...$proof, 'email' => ['required', 'email']], ['required' => 'Le champ :attribute du client doit être renseigné et validé.'])->validate();
        if ($customer['name'] !== trim($owner->first_name.' '.$owner->last_name)
            || $customer['address'] !== $owner->legal_address || $customer['nif'] !== $owner->getAttribute('nif')) {
            $this->fail('customer', 'Les informations fiscales du client ont changé depuis leur validation. Faites valider leur nouvelle version pour la facturation, sans nouvelle approbation du compte.');
        }
        if ($owner->legal_verification_status !== VerificationStatusEnum::VERIFIED || ! $owner->hasVerifiedEmail()) {
            $this->fail('customer', 'L’identité et l’adresse e-mail du client doivent être vérifiées avant facturation.');
        }
        Validator::make($policy, ['validation_reference' => ['required', 'string', 'max:255'],
            'validated_at' => ['required', 'date', 'before_or_equal:now'], 'rates' => ['present', 'array'],
            'no_tax_reason' => empty($policy['rates']) ? ['required', 'string', 'max:2000'] : ['nullable', 'string', 'max:2000']],
            ['required' => 'La politique fiscale doit renseigner :attribute avec une validation réelle.'])->validate();

        return [$issuer, $customer, $policy];
    }

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
        abort_if(tenancy()->initialized, 403);

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
                || $subscription->period_starts_at->isFuture() || $subscription->period_ends_at->lessThanOrEqualTo(now())
                || $subscription->agreed_amount === '0.00') {
                $this->fail('subscription', 'Choisissez la période en cours d’un abonnement payant actif.');
            }

            $rule = $this->lockRule($values['billing_rule_uuid']);
            $owner = $subscription->owner;
            abort_unless($owner instanceof User, 404);
            $due = now()->startOfSecond()->addDays($rule->parameters['payment_due_days']);
            $obligations = SubscriptionInstallment::where('parent_subscription_id', $subscription->id)
                ->where('period_starts_at', $subscription->period_starts_at)->where('period_ends_at', $subscription->period_ends_at)
                ->orderBy('id')->lockForUpdate()->get();
            if ($obligations->count() > 1) {
                $this->fail('installment', 'Cette période comporte plusieurs échéances historiques. Aucune nouvelle facture ne peut être préparée.');
            }
            $installment = $obligations->first();
            if ($installment !== null) {
                $this->assertFullPeriod($subscription, $installment);
                $existing = $installment->invoices()->lockForUpdate()->first();
                if ($existing !== null) {
                    $this->fail('conflict', 'Cette échéance possède déjà une facture. Consultez le document existant.');
                }
            }
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
        Validator::make($rule->only(['code', 'trigger_event', 'parameters']), BillingRuleService::contentRules())->validate();
        if (! $rule->isActiveAt(now())
            || $rule->parameters['document_type'] !== DocumentTypeEnum::INVOICE->value) {
            $this->fail('billing_rule_uuid', 'Choisissez une règle validée actuellement applicable aux factures d’abonnement.');
        }

        return $rule;
    }

    private function assertFullPeriod(Subscription $subscription, SubscriptionInstallment $installment): void
    {
        $obligations = SubscriptionInstallment::where('parent_subscription_id', $subscription->id)
            ->where('period_starts_at', $installment->period_starts_at)->where('period_ends_at', $installment->period_ends_at)
            ->orderBy('id')->lockForUpdate()->get(['id']);
        if ($obligations->count() !== 1 || ! $installment->matchesFullPeriod($subscription)
            || $installment->installment_status !== InstallmentStatusEnum::PENDING) {
            $this->fail('installment', 'La facture exige une échéance unique non réglée couvrant le montant intégral et la période de l’abonnement.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
