<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\PolicyStatusEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\BillingSequence;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingRuleService
{
    private const int PER_PAGE = 20;

    private const string EVENT_CREATED = 'saas_billing_rule_created';

    private const string EVENT_UPDATED = 'saas_billing_rule_updated';

    private const string EVENT_VALIDATED = 'saas_billing_rule_validated';

    private const string EVENT_ACTIVATED = 'saas_billing_rule_activated';

    private const string EVENT_RETIRED = 'saas_billing_rule_retired';

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /** @return array<string, mixed> */
    public static function contentRules(): array
    {
        $creditEvent = static fn (Fluent $input): bool => in_array($input->get('trigger_event'),
            [TriggerEventEnum::INVOICE_CORRECTION, TriggerEventEnum::INVOICE_CORRECTION->value], true);

        return [
            'code' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9_.-]*$/D'],
            'trigger_event' => ['required', Rule::enum(TriggerEventEnum::class)],
            'parameters' => ['required', 'array:document_type,payment_due_days'],
            'parameters.document_type' => ['required', 'integer', Rule::when(
                $creditEvent,
                [Rule::in([DocumentTypeEnum::CREDIT_NOTE->value])], [Rule::in([DocumentTypeEnum::INVOICE->value])])],
            'parameters.payment_due_days' => ['required', 'integer', 'min:0', Rule::when(
                $creditEvent, [Rule::in([0])])],
        ];
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, BillingRule>
     */
    public function search(User $actor, array $filters): LengthAwarePaginator
    {
        $this->assertCentralContext();
        Gate::forUser($actor)->authorize('viewAny', BillingRule::class);
        $search = trim((string) ($filters['search'] ?? ''));
        $periodStart = isset($filters['period_start']) ? CarbonImmutable::parse($filters['period_start']) : null;
        $periodEnd = isset($filters['period_end']) ? CarbonImmutable::parse($filters['period_end']) : null;

        return BillingRule::withExists('documents')
            ->when($search !== '', fn (Builder $query) => $query->where('code', 'like', '%'.addcslashes($search, '\\%_').'%'))
            ->when(isset($filters['policy_status']), fn (Builder $query) => $query->where('policy_status', $filters['policy_status']))
            ->when(isset($filters['version']), fn (Builder $query) => $query->where('version', $filters['version']))
            ->when($periodStart !== null, fn (Builder $query) => $query
                ->whereNotNull('effective_at')->where('effective_at', '<', $periodEnd)
                ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $periodStart)))
            ->orderBy('code')->orderByDesc('version')->paginate(self::PER_PAGE)->withQueryString();
    }

    /** @return LengthAwarePaginator<int, BillingSequence> */
    public function sequences(User $actor): LengthAwarePaginator
    {
        $this->assertCentralContext();
        Gate::forUser($actor)->authorize('viewAny', BillingSequence::class);

        return BillingSequence::orderByDesc('fiscal_year')->orderBy('document_type')->paginate(self::PER_PAGE);
    }

    /** @return LengthAwarePaginator<int, BillingRule> */
    public function versions(User $actor, BillingRule $rule): LengthAwarePaginator
    {
        $this->assertCentralContext();
        Gate::forUser($actor)->authorize('view', $rule);

        return BillingRule::where('code', $rule->code)->orderByDesc('version')
            ->paginate(self::PER_PAGE, ['*'], 'versions_page');
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data, string $operation): BillingRule
    {
        $this->assertCentralContext();

        return $this->mutations->run($actor, 'create', fn () => BillingRule::class,
            fn () => $this->insert($actor, $this->content($data), 1, $operation));
    }

    /** @param array<string, mixed> $data */
    public function createVersion(User $actor, BillingRule $source, array $data, string $expected, string $operation): BillingRule
    {
        $this->assertCentralContext();

        return $this->mutations->run($actor, 'createVersion', fn () => $this->lock($source, $expected),
            function (BillingRule $source) use ($actor, $data, $operation): BillingRule {
                $content = $this->content($data);
                if ($content['code'] !== $source->code) {
                    $this->fail('code', 'Une nouvelle version conserve le code de sa règle.');
                }
                $version = (int) BillingRule::where('code', $source->code)->max('version') + 1;

                return $this->insert($actor, $content, $version, $operation);
            });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, BillingRule $rule, array $data, string $expected): void
    {
        $this->assertCentralContext();
        $this->mutations->run($actor, 'update', fn () => $this->lock($rule, $expected),
            function (BillingRule $rule) use ($actor, $data): void {
                $content = $this->content($data);
                if ($content['code'] !== $rule->code) {
                    $this->fail('code', 'Le code de la règle est permanent.');
                }
                if ($rule->isUsed()) {
                    $this->fail('rule', 'Cette version est déjà utilisée. Créez une nouvelle version.');
                }
                $before = $this->snapshot($rule);
                $rule->forceFill($content);
                if (! $rule->isDirty()) {
                    return;
                }
                $rule->save();
                $this->audit($actor, $rule, self::EVENT_UPDATED, $before);
            });
    }

    public function validateRule(User $actor, BillingRule $rule, string $expected, string $reference): void
    {
        $this->assertCentralContext();
        $this->mutations->run($actor, 'validateRule', fn () => $this->lock($rule, $expected),
            function (BillingRule $rule) use ($actor, $reference): void {
                if ($rule->policy_status !== PolicyStatusEnum::DRAFT) {
                    $this->fail('rule', 'Seul un brouillon peut être validé.');
                }
                $this->content($rule->only(['code', 'trigger_event', 'parameters']));
                $values = Validator::make(['validation_reference' => trim($reference)], [
                    'validation_reference' => ['required', 'string', 'max:2000'],
                ])->validate();
                $before = $this->snapshot($rule);
                $rule->forceFill([
                    'policy_status' => PolicyStatusEnum::VALIDATED,
                    'validated_by_id' => $actor->id, 'validated_at' => now(),
                    'validation_reference' => $values['validation_reference'],
                ])->save();
                $this->audit($actor, $rule, self::EVENT_VALIDATED, $before);
            });
    }

    public function activate(User $actor, BillingRule $rule, string $expected, string $effective, ?string $ends): void
    {
        $this->assertCentralContext();
        $this->mutations->run($actor, 'activate', fn () => $this->lock($rule, $expected),
            function (BillingRule $rule) use ($actor, $effective, $ends): void {
                if ($rule->policy_status !== PolicyStatusEnum::VALIDATED || $rule->validated_at === null
                    || $rule->getAttribute('validated_by_id') === null || blank($rule->validation_reference)) {
                    $this->fail('rule', 'Validez cette version avant de l’activer.');
                }
                $values = Validator::make(['effective_at' => $effective, 'ends_at' => $ends], [
                    'effective_at' => ['required', 'date'],
                    'ends_at' => ['nullable', 'date', 'after:effective_at'],
                ])->validate();
                $start = CarbonImmutable::parse($values['effective_at']);
                $end = isset($values['ends_at']) ? CarbonImmutable::parse($values['ends_at']) : null;
                $this->assertNoOverlap($rule, $start, $end);
                $before = $this->snapshot($rule);
                $rule->forceFill([
                    'policy_status' => PolicyStatusEnum::ACTIVE, 'effective_at' => $start, 'ends_at' => $end,
                ])->save();
                $this->audit($actor, $rule, self::EVENT_ACTIVATED, $before);
            });
    }

    public function retire(User $actor, BillingRule $rule, string $expected, string $reason, string $ends): void
    {
        $this->assertCentralContext();
        $this->mutations->run($actor, 'activate', fn () => $this->lock($rule, $expected),
            function (BillingRule $rule) use ($actor, $reason, $ends): void {
                if ($rule->policy_status !== PolicyStatusEnum::ACTIVE) {
                    $this->fail('rule', 'Seule une version active peut être retirée.');
                }
                $values = Validator::make(['reason' => trim($reason), 'ends_at' => $ends], [
                    'reason' => ['required', 'string', 'max:2000'], 'ends_at' => ['required', 'date'],
                ])->validate();
                $end = CarbonImmutable::parse($values['ends_at']);
                if ($rule->ends_at !== null && $rule->ends_at->isPast()) {
                    $end = $rule->ends_at;
                } elseif ($end->isPast() || ($rule->ends_at !== null && $end->greaterThan($rule->ends_at))) {
                    $this->fail('ends_at', 'Choisissez une fermeture future, sans prolonger la période existante.');
                }
                if ($rule->effective_at === null || $end->lessThanOrEqualTo($rule->effective_at)) {
                    $this->fail('ends_at', 'La fermeture doit être postérieure à la date d’effet.');
                }
                $before = $this->snapshot($rule);
                $rule->forceFill(['policy_status' => PolicyStatusEnum::RETIRED, 'ends_at' => $end])->save();
                $this->audit($actor, $rule, self::EVENT_RETIRED, $before, $values['reason']);
            });
    }

    /** Toutes les versions d'un code prennent d'abord le verrou de sa première ligne stable. */
    private function lock(BillingRule $rule, string $expected): BillingRule
    {
        BillingRule::where('code', $rule->code)->orderBy('version')->lockForUpdate()->firstOrFail();
        $current = BillingRule::whereKey($rule->id)->lockForUpdate()->firstOrFail()->loadExists('documents');
        if (! hash_equals($current->managementState(), $expected)) {
            $this->fail('conflict', 'Cette règle a changé. Rechargez le formulaire avant de recommencer.');
        }

        return $current;
    }

    /** @param array<string, mixed> $content */
    private function insert(User $actor, array $content, int $version, string $operation): BillingRule
    {
        Validator::make(['operation_uuid' => $operation], ['operation_uuid' => ['required', 'uuid:4']])->validate();
        $key = 'saas:rule:'.$actor->uuid.':'.strtolower($operation);
        $existing = BillingRule::where('operation_key', $key)->first();
        if ($existing !== null) {
            if ($this->content($existing->only(['code', 'trigger_event', 'parameters'])) !== $content) {
                $this->fail('conflict', 'Cette demande a déjà été utilisée avec un autre contenu.');
            }

            return $existing;
        }
        $rule = new BillingRule;
        $rule->forceFill([
            ...$content, 'version' => $version, 'numbering_scope' => BillingRule::NUMBERING_SCOPE,
            'policy_status' => PolicyStatusEnum::DRAFT, 'created_by_id' => $actor->id,
            'operation_key' => $key, 'correlation_id' => (string) Str::uuid(),
        ]);
        try {
            $rule->save();
        } catch (UniqueConstraintViolationException) {
            $this->fail('code', 'Ce code et cette version existent déjà. Rechargez la page avant de recommencer.');
        }
        $this->audit($actor, $rule, self::EVENT_CREATED, null);

        return $rule->refresh();
    }

    /** @param array<string, mixed> $data
     * @return array{code: string, trigger_event: string, parameters: array{document_type: int, payment_due_days: int}}
     */
    private function content(array $data): array
    {
        if (($data['trigger_event'] ?? null) instanceof TriggerEventEnum) {
            $data['trigger_event'] = $data['trigger_event']->value;
        }
        $data = Validator::make($data, self::contentRules())->validate();

        return [
            'code' => $data['code'], 'trigger_event' => $data['trigger_event'],
            'parameters' => ['document_type' => (int) $data['parameters']['document_type'],
                'payment_due_days' => (int) $data['parameters']['payment_due_days']],
        ];
    }

    private function assertNoOverlap(BillingRule $rule, CarbonImmutable $start, ?CarbonImmutable $end): void
    {
        $overlap = BillingRule::where('code', $rule->code)->whereKeyNot($rule->id)
            ->whereIn('policy_status', [PolicyStatusEnum::ACTIVE, PolicyStatusEnum::RETIRED])
            ->whereNotNull('effective_at')
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $start))
            ->when($end !== null, fn (Builder $query) => $query->where('effective_at', '<', $end))
            ->exists();
        if ($overlap) {
            $this->fail('effective_at', 'Cette période chevauche une autre version du même code. Fermez sa période avant d’activer cette version.');
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(BillingRule $rule): array
    {
        return $rule->only([...BillingRule::CONTENT_FIELDS, 'policy_status', 'effective_at', 'ends_at',
            'validated_by_id', 'validated_at', 'validation_reference']);
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $actor, BillingRule $rule, string $event, ?array $before, ?string $reason = null): void
    {
        activity('central')->causedBy($actor)->performedOn($rule)->event($event)
            ->tap(fn (Activity $activity) => $activity->forceFill(['correlation_id' => $rule->correlation_id]))
            ->withProperties(['before' => $before, 'after' => $this->snapshot($rule),
                'reason' => $reason, 'correlation_id' => $rule->correlation_id])
            ->log('Configuration de facturation SaaS.');
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
