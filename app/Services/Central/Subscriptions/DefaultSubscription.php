<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Subscription;
use App\Models\Central\User;
use App\Services\Central\Plans\FreePlanCatalog;
use Illuminate\Support\Facades\DB;
use LogicException;

class DefaultSubscription
{
    private const string KEY_PREFIX = 'default-free:';

    private const string INITIAL = 'initial';

    private const string FREE_AMOUNT = '0.00';

    private const int ATTEMPTS = 3;

    public function __construct(private FreePlanCatalog $catalog) {}

    public function ensure(User $owner, ?User $assignedBy = null): Subscription
    {
        if (tenancy()->initialized) {
            throw new LogicException('Subscriptions require the central context.');
        }

        return DB::transaction(function () use ($owner, $assignedBy): Subscription {
            User::whereKey($owner->id)->lockForUpdate()->firstOrFail();

            $current = $this->currentOf($owner);

            if ($current !== null) {
                if ($current->period_starts_at->isFuture()) {
                    throw new LogicException('An active subscription cannot start in the future.');
                }

                if ($current->period_ends_at === null || $current->period_ends_at->isFuture()) {
                    return $current;
                }

                $current->update(['status' => StatusEnum::EXPIRED, 'ended_at' => now()]);
            }

            $previous = $current ?? Subscription::where('user_id', $owner->id)
                ->orderByDesc('id')->lockForUpdate()->first();

            return $this->createFree($owner, $assignedBy, $previous);
        }, self::ATTEMPTS);
    }

    private function currentOf(User $owner): ?Subscription
    {
        return Subscription::where('user_id', $owner->id)
            ->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    private function createFree(User $owner, ?User $assignedBy, ?Subscription $previous): Subscription
    {
        return Subscription::create([
            'user_id' => $owner->id,
            'plan_id' => $this->catalog->current()->id,
            'assigned_by_id' => $assignedBy?->id,
            'operation_key' => self::KEY_PREFIX.$owner->uuid.':'.($previous->uuid ?? self::INITIAL),
            'status' => StatusEnum::ACTIVE,
            'period' => PeriodEnum::MONTHLY,
            'agreed_amount' => self::FREE_AMOUNT,
            'started_at' => now(),
            'period_starts_at' => now(),
            'period_ends_at' => null,
            'auto_renew' => false,
        ]);
    }
}
