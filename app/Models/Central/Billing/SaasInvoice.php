<?php

namespace App\Models\Central\Billing;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Models\Central\Media\Media;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $subscription_id
 * @property int $installment_id
 * @property int $billing_rule_id
 * @property int|null $sequence_id
 * @property string|null $number
 * @property int|null $sequence_number
 * @property string $net_amount
 * @property string $tax_amount
 * @property string $total_amount
 * @property string $currency
 * @property DocumentStatusEnum $status
 * @property DocumentTypeEnum $document_type
 * @property array<string, mixed> $billing_rule_snapshot
 * @property array<string, mixed> $saas_identity_snapshot
 * @property array<string, mixed> $customer_identity_snapshot
 * @property CarbonImmutable $period_starts_at
 * @property CarbonImmutable $period_ends_at
 * @property CarbonImmutable $due_at
 * @property-read User $owner
 * @property-read Subscription $subscription
 * @property-read SubscriptionInstallment $installment
 * @property-read BillingRule $billingRule
 * @property-read BillingSequence|null $sequence
 */
#[Fillable([])]
class SaasInvoice extends Model
{
    use CentralConnection, HasPublicUuid;

    public const string CURRENCY = 'DZD';

    protected static function booted(): void
    {
        static::addGlobalScope('invoice', fn (Builder $query) => $query->where('document_type', DocumentTypeEnum::INVOICE->value));
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatusEnum::class,
            'document_type' => DocumentTypeEnum::class,
            'fiscal_year' => 'integer', 'sequence_number' => 'integer',
            'net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2',
            'billing_rule_snapshot' => 'array', 'taxes' => 'array',
            'saas_identity_snapshot' => 'array', 'customer_identity_snapshot' => 'array',
            'period_starts_at' => 'immutable_datetime', 'period_ends_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime', 'issued_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<SubscriptionInstallment, $this> */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInstallment::class, 'installment_id');
    }

    /** @return BelongsTo<BillingRule, $this> */
    public function billingRule(): BelongsTo
    {
        return $this->belongsTo(BillingRule::class);
    }

    /** @return BelongsTo<BillingSequence, $this> */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(BillingSequence::class);
    }

    /** @return HasMany<SaasInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SaasInvoiceLine::class, 'document_id')->orderBy('line_number');
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'document_media_id');
    }

    /** @return HasMany<SaasDocumentDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(SaasDocumentDelivery::class, 'document_id');
    }

    public function managementState(): string
    {
        return hash('sha256', json_encode($this->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function getMorphClass(): string
    {
        return 'saas_invoice';
    }
}
