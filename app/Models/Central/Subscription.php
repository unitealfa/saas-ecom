<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use Carbon\CarbonImmutable;
use Database\Factories\Central\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property StatusEnum $status
 * @property CarbonImmutable $period_starts_at
 * @property CarbonImmutable|null $period_ends_at
 */
#[Fillable([
    'user_id', 'plan_id', 'assigned_by_id', 'operation_key', 'status', 'period', 'agreed_amount',
    'started_at', 'period_starts_at', 'period_ends_at', 'trial_ends_at', 'ended_at', 'auto_renew',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use CentralConnection, HasFactory, HasPublicUuid;

    public const int RECORD_TYPE = 1;

    public const string SCOPE = 'subscription';

    protected $attributes = ['record_type' => self::RECORD_TYPE];

    protected static function booted(): void
    {
        static::addGlobalScope(
            self::SCOPE,
            fn (Builder $query) => $query->where('record_type', self::RECORD_TYPE),
        );
    }

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
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id')->withTrashed();
    }
}
