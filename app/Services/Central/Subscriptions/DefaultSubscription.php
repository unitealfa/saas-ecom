<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Subscription;
use App\Models\Central\User;
use App\Services\Central\Plans\FreePlanCatalog;
use LogicException;

class DefaultSubscription
{
    public function __construct(private FreePlanCatalog $catalog) {}

    public function ensure(User $owner, ?User $assignedBy = null): Subscription
    {
        if (tenancy()->initialized) {
            throw new LogicException('Subscriptions require the central context.');
        }

        return $owner->getConnection()->transaction(function () use ($owner, $assignedBy): Subscription {
            User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $current = Subscription::where('user_id', $owner->id)
                ->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL])->orderByDesc('id')->lockForUpdate()->first();
            if ($current !== null) {
                if ($current->period_starts_at->isFuture()) {
                    throw new LogicException('An active subscription cannot start in the future.');
                }
                if ($current->period_ends_at === null || $current->period_ends_at->isFuture()) {
                    return $current;
                }
                $current->update(['status' => StatusEnum::EXPIRED, 'ended_at' => now()]);
            }

            $plan = $this->catalog->current();

            return Subscription::create([
                'user_id' => $owner->id, 'plan_id' => $plan->id, 'assigned_by_id' => $assignedBy?->id,
                'operation_key' => 'default-free:'.$owner->uuid.':'.($current->uuid ?? 'initial'), 'status' => StatusEnum::ACTIVE,
                'period' => PeriodEnum::MONTHLY, 'agreed_amount' => '0.00',
                'started_at' => now(), 'period_starts_at' => now(), 'period_ends_at' => null, 'auto_renew' => false,
            ]);
        }, 3);
    }
}
