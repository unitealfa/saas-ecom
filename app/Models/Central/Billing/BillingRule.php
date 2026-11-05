<?php

namespace App\Models\Central\Billing;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Billing\PolicyStatusEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Models\Central\Users\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use LogicException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property int $version
 * @property int|null $created_by_id
 * @property PolicyStatusEnum $policy_status
 * @property TriggerEventEnum $trigger_event
 * @property array{document_type: int, payment_due_days: int} $parameters
 * @property CarbonImmutable|null $effective_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $validated_at
 * @property string|null $validation_reference
 * @property string $operation_key
 * @property string $correlation_id
 * @property-read User|null $creator
 * @property-read User|null $validator
 */
#[Fillable([])]
class BillingRule extends Model
{
    use CentralConnection, HasPublicUuid;

    public const int RECORD_TYPE = 2;

    public const string NUMBERING_SCOPE = 'saas_issuer';

    public const string STATE_PATTERN = '/^[a-f0-9]{64}$/D';

    public const array CONTENT_FIELDS = ['code', 'version', 'trigger_event', 'numbering_scope', 'parameters'];

    private const array IDENTITY = ['record_type', 'code', 'version', 'operation_key', 'correlation_id', 'created_by_id'];

    protected $table = 'saas_billing_settings';

    protected $attributes = ['record_type' => self::RECORD_TYPE];

    protected static function booted(): void
    {
        static::addGlobalScope('billing_rule', fn (Builder $query) => $query->where('record_type', self::RECORD_TYPE));
        static::updating(function (self $rule): void {
            if ($rule->isDirty(self::IDENTITY)) {
                throw new LogicException('The identity of a billing rule is immutable.');
            }
            if ($rule->isDirty(self::CONTENT_FIELDS)
                && ((int) $rule->getRawOriginal('policy_status') !== PolicyStatusEnum::DRAFT->value || $rule->documents()->exists())) {
                throw new LogicException('Validated billing content requires a new version.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'created_by_id' => 'integer',
            'policy_status' => PolicyStatusEnum::class,
            'trigger_event' => TriggerEventEnum::class,
            'parameters' => 'array',
            'effective_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'validated_at' => 'immutable_datetime',
        ];
    }

    public function getMorphClass(): string
    {
        return 'saas_billing_rule';
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_id')->withTrashed();
    }

    public function managementState(): string
    {
        return hash('sha256', json_encode(Arr::except($this->getAttributes(), ['documents_exists']), JSON_THROW_ON_ERROR));
    }

    /** @return HasMany<SaasInvoice, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(SaasInvoice::class, 'billing_rule_id')->withoutGlobalScope('invoice');
    }

    public function isUsed(): bool
    {
        return array_key_exists('documents_exists', $this->getAttributes())
            ? (bool) $this->getAttribute('documents_exists') : $this->documents()->exists();
    }

    public function isActiveAt(CarbonInterface $date): bool
    {
        return $this->policy_status === PolicyStatusEnum::ACTIVE && $this->isApplicableAt($date);
    }

    public function isApplicableAt(CarbonInterface $date): bool
    {
        return in_array($this->policy_status, [PolicyStatusEnum::ACTIVE, PolicyStatusEnum::RETIRED], true)
            && $this->validated_at !== null && $this->getAttribute('validated_by_id') !== null
            && filled($this->validation_reference) && $this->effective_at !== null
            && $this->effective_at->lessThanOrEqualTo($date)
            && ($this->ends_at === null || $this->ends_at->greaterThan($date));
    }

    public function isActiveAt(CarbonInterface $date): bool
    {
        return $this->policy_status === PolicyStatusEnum::ACTIVE && $this->isApplicableAt($date);
    }
}
