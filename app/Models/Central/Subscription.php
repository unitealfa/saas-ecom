<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use Carbon\CarbonImmutable;
use Database\Factories\Central\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property StatusEnum $status
 * @property PeriodEnum $period
 * @property CarbonImmutable $period_starts_at
 * @property CarbonImmutable|null $period_ends_at
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use CentralConnection, HasFactory, HasPublicUuid;

    protected $fillable = ['user_id', 'plan_id', 'assigned_by_id', 'operation_key', 'status', 'period', 'agreed_amount', 'started_at', 'period_starts_at', 'period_ends_at', 'trial_ends_at', 'ended_at', 'auto_renew'];

    protected $attributes = ['record_type' => 1];

    protected static function booted(): void
    {
        static::addGlobalScope('subscription', fn (Builder $query) => $query->where('record_type', 1));
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'plan_id' => 'integer',
            'assigned_by_id' => 'integer',
            'status' => StatusEnum::class,
            'period' => PeriodEnum::class,
            'agreed_amount' => 'decimal:2',
            'auto_renew' => 'boolean',
            'started_at' => 'immutable_datetime',
            'period_starts_at' => 'immutable_datetime',
            'period_ends_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
