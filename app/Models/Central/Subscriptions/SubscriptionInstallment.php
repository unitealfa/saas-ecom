<?php

namespace App\Models\Central\Subscriptions;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Models\Central\Billing\SaasInvoice;
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
 * @property int $parent_subscription_id
 * @property string $installment_number
 * @property string $installment_amount
 * @property string $operation_key
 * @property InstallmentStatusEnum $installment_status
 * @property CarbonImmutable $period_starts_at
 * @property CarbonImmutable $period_ends_at
 * @property CarbonImmutable $due_at
 * @property-read Subscription $subscription
 * @property-read User $owner
 */
#[Fillable([])]
class SubscriptionInstallment extends Model
{
    use CentralConnection, HasPublicUuid;

    public const int RECORD_TYPE = 2;

    protected $table = 'subscriptions';

    protected $attributes = ['record_type' => self::RECORD_TYPE];

    protected static function booted(): void
    {
        static::addGlobalScope('installment', fn (Builder $query) => $query->where('record_type', self::RECORD_TYPE));
    }

    protected function casts(): array
    {
        return [
            'installment_status' => InstallmentStatusEnum::class,
            'installment_amount' => 'decimal:2',
            'period_starts_at' => 'immutable_datetime',
            'period_ends_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'parent_subscription_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** @return HasMany<SaasInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class, 'installment_id');
    }

    public function getMorphClass(): string
    {
        return 'subscription_installment';
    }

    public function matchesFullPeriod(Subscription $subscription): bool
    {
        return $subscription->period_ends_at !== null
            && $this->parent_subscription_id === $subscription->id
            && $this->user_id === $subscription->user_id
            && $this->installment_amount === $subscription->agreed_amount
            && $this->period_starts_at->equalTo($subscription->period_starts_at)
            && $this->period_ends_at->equalTo($subscription->period_ends_at);
    }
}
