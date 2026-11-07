<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasCreditNoteLine;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasInvoiceLine;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaasCreditNoteService
{
    public const string CREATED = 'saas_credit_note_created';

    public const string UPDATED = 'saas_credit_note_updated';

    public const string CANCELLED = 'saas_credit_note_cancelled';

    private const array DIMENSIONS = ['quantity', 'net_amount', 'tax_amount', 'total_amount'];

    public function __construct(private readonly AuthorizationMutation $mutations, private readonly InvoiceCalculator $calculator) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SaasCreditNote>
     */
    public function search(User $actor, array $filters, bool $own = false): LengthAwarePaginator
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize($own ? 'viewOwn' : 'viewAny', SaasCreditNote::class);
        $search = trim($filters['search'] ?? '');

        return SaasCreditNote::with(['originalInvoice', 'owner'])
            ->when($own, fn (Builder $query) => $query->where('user_id', $actor->id)->where('status', DocumentStatusEnum::ISSUED))
            ->when(! $own && isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('number', 'like', '%'.addcslashes($search, '\\%_').'%')
                ->orWhere('uuid', $search)->orWhereRelation('originalInvoice', 'number', 'like', '%'.addcslashes($search, '\\%_').'%')))
            ->orderByDesc('id')->paginate(20)->withQueryString();
    }

    /** @return array<string, mixed> */
    public static function rules(bool $creating): array
    {
        return ['expected_state' => ['required', 'string', 'regex:'.SaasCreditNote::STATE_PATTERN],
            'operation_id' => $creating ? ['required', 'uuid:4'] : ['missing'],
            'billing_rule_uuid' => ['required', 'uuid:4'], 'reason' => ['required', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*' => ['array:original_line_uuid,quantity,total_amount,reason'],
            'lines.*.original_line_uuid' => ['required', 'uuid:4', 'distinct:ignore_case'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'gt:0'],
            'lines.*.total_amount' => ['required', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'],
            'lines.*.reason' => ['required', 'string', 'max:2000'],
            ...array_fill_keys(['user_id', 'original_invoice_id', 'subscription_id', 'installment_id', 'document_type', 'status', 'currency',
                'net_amount', 'taxes', 'tax_amount', 'total_amount', 'number', 'sequence_id', 'document_media_id', 'operation_key', 'issued_at'], ['missing'])];
    }

    /** @param array<string, mixed> $data */
    public function createCreditNoteDraft(User $actor, SaasInvoice $original, array $data): SaasCreditNote
    {
        abort_if(tenancy()->initialized, 403);

        return $this->mutations->run($actor, 'create', fn () => SaasCreditNote::class, function () use ($actor, $original, $data): SaasCreditNote {
            $values = Validator::make($data, self::rules(true))->validate();
            $original = $this->lockOriginal($original);
            $key = 'saas:credit:'.$original->uuid.':'.strtolower($values['operation_id']);
            $payload = hash('sha256', json_encode(collect($values)->except(['expected_state', 'operation_id'])->all(), JSON_THROW_ON_ERROR));
            $existing = SaasCreditNote::where('operation_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                if (($existing->billing_rule_snapshot['request_hash'] ?? null) !== $payload) {
                    $this->fail('Cette opération a déjà été utilisée pour une autre correction.');
                }

                return $existing;
            }
            $this->assertState($original, $values['expected_state']);
            $rule = $this->lockRule($values['billing_rule_uuid']);
            $credit = new SaasCreditNote;
            $credit->forceFill([...$original->only(['user_id', 'subscription_id', 'installment_id', 'currency', 'period_starts_at', 'period_ends_at',
                'saas_identity_snapshot', 'customer_identity_snapshot']), 'original_invoice_id' => $original->id,
                'document_type' => DocumentTypeEnum::CREDIT_NOTE, 'status' => DocumentStatusEnum::DRAFT,
                'billing_rule_id' => $rule->id, 'billing_rule_snapshot' => [...$rule->only(['uuid', 'code', 'version', 'trigger_event', 'parameters',
                    'validation_reference', 'validated_at', 'effective_at', 'ends_at']),
                    'fiscal_policy' => $original->billing_rule_snapshot['fiscal_policy'] ?? [], 'request_hash' => $payload],
                'due_at' => null, 'reason' => trim($values['reason']), 'operation_key' => $key, 'correlation_id' => (string) Str::uuid(),
                'net_amount' => '0.00', 'taxes' => [], 'tax_amount' => '0.00', 'total_amount' => '0.00'])->save();
            $this->reserveCorrection($credit, $original, $values['lines']);
            $this->audit($actor, $credit, self::CREATED);

            return $credit->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function updateDraft(User $actor, SaasCreditNote $credit, array $data): SaasCreditNote
    {
        abort_if(tenancy()->initialized, 403);

        return $this->mutations->run($actor, 'update', fn () => $this->lockCredit($credit), function (SaasCreditNote $credit) use ($actor, $data): SaasCreditNote {
            $values = Validator::make($data, self::rules(false))->validate();
            $this->assertState($credit, $values['expected_state']);
            if ($credit->status !== DocumentStatusEnum::DRAFT) {
                $this->fail('Seul un brouillon non numéroté peut être modifié.');
            }
            $rule = $this->lockRule($values['billing_rule_uuid']);
            if ($rule->id !== $credit->billing_rule_id) {
                $this->fail('La règle reste celle de ce brouillon. Annulez-le pour choisir une autre règle.');
            }
            $credit->forceFill(['reason' => trim($values['reason'])])->save();
            $this->reserveCorrection($credit, $credit->originalInvoice, $values['lines']);
            $this->audit($actor, $credit, self::UPDATED);

            return $credit->refresh();
        });
    }

    public function cancelDraft(User $actor, SaasCreditNote $credit, string $expected, string $reason): void
    {
        abort_if(tenancy()->initialized, 403);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:2000']])->validate();
        $this->mutations->run($actor, 'cancel', fn () => $this->lockCredit($credit), function (SaasCreditNote $credit) use ($actor, $expected, $reason): void {
            $this->assertState($credit, $expected);
            if (! in_array($credit->status, [DocumentStatusEnum::DRAFT, DocumentStatusEnum::PREPARING], true)) {
                $this->fail('Un avoir émis ne peut pas être annulé pour libérer sa correction.');
            }
            $credit->forceFill(['status' => DocumentStatusEnum::CANCELLED, 'cancelled_at' => now(), 'cancellation_reason' => trim($reason)])->save();
            $this->audit($actor, $credit, self::CANCELLED);
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function availableLines(User $actor, SaasInvoice $original, ?SaasCreditNote $editing = null): array
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('inspectOriginal', [SaasCreditNote::class, $original]);

        return $this->mutations->transaction(function () use ($original, $editing): array {
            $original = $this->lockOriginal($original);

            return $original->lines->mapWithKeys(fn (SaasInvoiceLine $line): array => [$line->id => ['line' => $line, ...$this->calculateRemainingCorrection($line, $editing?->id)]])->all();
        });
    }

    public function assertIssuable(SaasInvoice $credit): void
    {
        if (! $credit instanceof SaasCreditNote) {
            $this->fail('Le document à émettre doit être un avoir.');
        }
        $original = $this->lockOriginal($credit->originalInvoice);
        $this->validateOriginalInvoice($original);
        foreach (['user_id', 'subscription_id', 'installment_id', 'currency'] as $field) {
            if ($credit->getAttribute($field) !== $original->getAttribute($field)) {
                $this->fail('L’avoir et sa facture ne désignent pas la même origine.');
            }
        }
        if (! $credit->period_starts_at->equalTo($original->period_starts_at) || ! $credit->period_ends_at->equalTo($original->period_ends_at)
            || trim((string) $credit->getAttribute('reason')) === '' || $credit->due_at !== null) {
            $this->fail('La période, le motif ou la date limite de l’avoir est incohérent.');
        }
        if ($credit->status === DocumentStatusEnum::DRAFT) {
            $this->lockRule($credit->billingRule->uuid);
        }
        $lines = $credit->lines()->orderBy('id')->lockForUpdate()->get();
        if ($lines->isEmpty()) {
            $this->fail('Une correction exige au moins une ligne.');
        }
        $totals = ['net_amount' => '0.00', 'tax_amount' => '0.00', 'total_amount' => '0.00'];
        $taxes = [];
        foreach ($lines as $line) {
            $origin = $original->lines->firstWhere('id', $line->getAttribute('original_invoice_line_id'));
            if ($origin === null) {
                $this->fail('Une ligne ne provient pas de la facture originale.');
            }
            $this->assertWithinLimits($origin, $line->getAttributes(), $credit->id);
            $computed = $this->correctionAmounts($origin, $line->total_amount);
            foreach (['net_amount', 'tax_amount', 'total_amount'] as $field) {
                if ($computed[$field] !== $line->getAttribute($field)) {
                    $this->fail('Le calcul fiscal de la ligne ne correspond pas à la ventilation originale.');
                }
                $totals[$field] = (string) BigDecimal::of($totals[$field])->plus($computed[$field]);
            }
            if ($line->getAttribute('taxes') !== $computed['taxes']) {
                $this->fail('Les taxes de la correction ont changé.');
            }
            $this->accumulateTaxes($taxes, $computed['taxes']);
        }
        foreach ($totals as $field => $total) {
            if ($total !== $credit->getAttribute($field)) {
                $this->fail('Le total de l’avoir ne correspond pas aux lignes.');
            }
        }
        if (array_values($taxes) !== $credit->getAttribute('taxes')) {
            $this->fail('La ventilation des taxes de l’avoir est incohérente.');
        }
    }

    /** @param array<array-key, array<string, mixed>> $inputs */
    private function reserveCorrection(SaasCreditNote $credit, SaasInvoice $original, array $inputs): void
    {
        $credit->lines()->delete();
        $totals = ['net_amount' => '0.00', 'tax_amount' => '0.00', 'total_amount' => '0.00'];
        $taxes = [];
        foreach (array_values($inputs) as $index => $input) {
            $origin = $original->lines->first(fn (SaasInvoiceLine $line): bool => strtolower($line->getAttribute('uuid')) === strtolower($input['original_line_uuid']));
            if ($origin === null) {
                $this->fail('Sélectionnez uniquement les lignes de la facture originale.');
            }
            $amounts = $this->correctionAmounts($origin, $input['total_amount']);
            $quantity = (string) BigDecimal::of($input['quantity'])->toScale(2);
            $this->assertWithinLimits($origin, [...$amounts, 'quantity' => $quantity], $credit->id);
            (new SaasCreditNoteLine)->forceFill(['document_id' => $credit->id, 'user_id' => $original->user_id,
                'document_type' => DocumentTypeEnum::CREDIT_NOTE, 'original_invoice_id' => $original->id, 'original_invoice_line_id' => $origin->id,
                'line_number' => $index + 1, 'quantity' => $quantity, 'description' => $origin->description, 'reason' => trim($input['reason']),
                'net_unit_price' => null, 'net_discount' => null, ...$amounts,
                'operation_key' => 'saas:line:'.$credit->uuid.':'.$origin->getAttribute('uuid'), 'correlation_id' => $credit->getAttribute('correlation_id')])->save();
            foreach ($totals as $field => $sum) {
                $totals[$field] = (string) BigDecimal::of($sum)->plus($amounts[$field]);
            }
            $this->accumulateTaxes($taxes, $amounts['taxes']);
        }
        $credit->forceFill([...$totals, 'taxes' => array_values($taxes)])->save();
    }

    /** @return array<string, mixed> */
    private function correctionAmounts(SaasInvoiceLine $original, string $total): array
    {
        $rates = [];
        foreach ($original->getAttribute('taxes') as $item) {
            if (! is_array($item) || ! is_string($item['code'] ?? null) || ! is_string($item['rate'] ?? null)) {
                $this->fail('La ventilation fiscale originale n’est pas exploitable.');
            }
            $rates[] = ['code' => $item['code'], 'rate' => $item['rate']];
        }

        return collect($this->calculator->fromTotal((string) BigDecimal::of($total)->toScale(2), $original->description, $rates))
            ->only(['net_amount', 'taxes', 'tax_amount', 'total_amount'])->all();
    }

    /** @return array{remaining: array<string, string>, reserved: array<string, string>, issued: array<string, string>, remaining_taxes: array<string, string>} */
    private function calculateRemainingCorrection(SaasInvoiceLine $original, ?int $exclude): array
    {
        $result = ['remaining' => [], 'reserved' => [], 'issued' => [], 'remaining_taxes' => []];
        $lines = SaasCreditNoteLine::join('saas_invoices as credits', 'credits.id', '=', 'saas_invoice_lines.document_id')
            ->where('original_invoice_line_id', $original->id)->when($exclude !== null, fn (Builder $q) => $q->where('document_id', '!=', $exclude))
            ->where('credits.document_type', DocumentTypeEnum::CREDIT_NOTE)
            ->whereIn('credits.status', [DocumentStatusEnum::DRAFT, DocumentStatusEnum::PREPARING, DocumentStatusEnum::ISSUED])
            ->select('saas_invoice_lines.*', 'credits.status as correction_status')->orderBy('saas_invoice_lines.id')->lockForUpdate()->get();
        foreach (self::DIMENSIONS as $field) {
            $reserved = BigDecimal::of('0.00');
            $issued = BigDecimal::of('0.00');
            foreach ($lines as $line) {
                if ((int) $line->getAttribute('correction_status') === DocumentStatusEnum::ISSUED->value) {
                    $issued = $issued->plus($line->getAttribute($field));
                } else {
                    $reserved = $reserved->plus($line->getAttribute($field));
                }
            }
            $result['reserved'][$field] = (string) $reserved;
            $result['issued'][$field] = (string) $issued;
            $result['remaining'][$field] = (string) BigDecimal::of($original->getAttribute($field))->minus($reserved)->minus($issued);
        }
        foreach ($original->getAttribute('taxes') as $tax) {
            $remaining = BigDecimal::of($tax['amount']);
            foreach ($lines as $line) {
                foreach ($line->getAttribute('taxes') as $correction) {
                    if ($correction['code'] === $tax['code']) {
                        $remaining = $remaining->minus($correction['amount']);
                    }
                }
            }
            $result['remaining_taxes'][$tax['code']] = (string) $remaining;
        }

        return $result;
    }

    /** @param array<string, mixed> $values */
    private function assertWithinLimits(SaasInvoiceLine $original, array $values, int $exclude): void
    {
        $limits = $this->calculateRemainingCorrection($original, $exclude);
        $available = $limits['remaining'];
        foreach (self::DIMENSIONS as $field) {
            $amount = BigDecimal::of($values[$field]);
            if ($amount->isNegative() || ($field === 'quantity' && $amount->isZero()) || $amount->isGreaterThan($available[$field])) {
                $this->fail('La correction dépasse le disponible de la ligne pour '.$field.'. Rechargez les montants disponibles.');
            }
        }
        $taxes = is_string($values['taxes']) ? json_decode($values['taxes'], true, flags: JSON_THROW_ON_ERROR) : $values['taxes'];
        foreach ($taxes as $tax) {
            if (! isset($limits['remaining_taxes'][$tax['code']]) || BigDecimal::of($tax['amount'])->isGreaterThan($limits['remaining_taxes'][$tax['code']])) {
                $this->fail('La correction dépasse le disponible de la taxe '.$tax['code'].'.');
            }
        }
    }

    private function lockCredit(SaasCreditNote $credit): SaasCreditNote
    {
        $original = $this->lockOriginal($credit->originalInvoice);
        $current = SaasCreditNote::whereKey($credit->id)->lockForUpdate()->firstOrFail();

        return $current->setRelation('originalInvoice', $original);
    }

    private function lockOriginal(SaasInvoice $original): SaasInvoice
    {
        User::withTrashed()->whereKey($original->user_id)->lockForUpdate()->firstOrFail();
        Subscription::where('user_id', $original->user_id)->orderBy('id')->lockForUpdate()->get();
        SubscriptionInstallment::whereKey($original->installment_id)->lockForUpdate()->firstOrFail();
        $original = SaasInvoice::whereKey($original->id)->lockForUpdate()->firstOrFail();
        $this->validateOriginalInvoice($original);

        return $original->setRelation('lines', $original->lines()->orderBy('id')->lockForUpdate()->get());
    }

    private function validateOriginalInvoice(SaasInvoice $original): void
    {
        if ($original->document_type !== DocumentTypeEnum::INVOICE || $original->status !== DocumentStatusEnum::ISSUED) {
            $this->fail('Un avoir corrige uniquement une facture originale déjà émise.');
        }
    }

    private function lockRule(string $uuid): BillingRule
    {
        $candidate = BillingRule::where('uuid', strtolower($uuid))->firstOrFail();
        BillingRule::where('code', $candidate->code)->orderBy('version')->lockForUpdate()->firstOrFail();
        $rule = BillingRule::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
        if (! $rule->isActiveAt(now()) || $rule->trigger_event !== TriggerEventEnum::INVOICE_CORRECTION
            || ($rule->parameters['document_type'] ?? null) !== DocumentTypeEnum::CREDIT_NOTE->value) {
            $this->fail('Choisissez une règle active validée pour la correction des factures.');
        }
        Validator::make($rule->only(['code', 'trigger_event', 'parameters']), BillingRuleService::contentRules())->validate();

        return $rule;
    }

    private function assertState(SaasInvoice $document, string $expected): void
    {
        if (! hash_equals($document->managementState(), $expected)) {
            $this->fail('Le document a changé. Rechargez avant de poursuivre.');
        }
    }

    /** @param array<string, array<string, string>> $totals
     * @param  list<array<string, string>>  $taxes
     */
    private function accumulateTaxes(array &$totals, array $taxes): void
    {
        foreach ($taxes as $tax) {
            $totals[$tax['code']] = [...$tax, 'amount' => (string) BigDecimal::of($totals[$tax['code']]['amount'] ?? '0.00')->plus($tax['amount'])];
        }
    }

    private function audit(User $actor, SaasCreditNote $credit, string $event): void
    {
        activity('central')->causedBy($actor)->performedOn($credit)->event($event)
            ->tap(fn ($activity) => $activity->forceFill(['correlation_id' => $credit->getAttribute('correlation_id')]))
            ->withProperties(['credit_uuid' => $credit->uuid, 'original_invoice_uuid' => $credit->originalInvoice->uuid,
                'amount' => $credit->total_amount, 'currency' => $credit->currency, 'reason' => $credit->getAttribute('reason'),
                'lines' => $credit->lines()->get()->map(fn (SaasInvoiceLine $line): array => $line->only([
                    'uuid', 'original_invoice_line_id', 'quantity', 'net_amount', 'taxes', 'tax_amount', 'total_amount', 'reason',
                ]))->all()])->log('Correction fiscale : '.$credit->getAttribute('reason'));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['credit' => $message]);
    }
}
