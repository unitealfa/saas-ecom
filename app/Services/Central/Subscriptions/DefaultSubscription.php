<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use App\Services\Central\Plans\FreePlanCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;

class DefaultSubscription
{
    public const string EFFECTIVE_CHANGED = 'central.subscription.effective_changed';

    private const string KEY_PREFIX = 'default-free:';

    private const string INITIAL = 'initial';
    private const int ATTEMPTS = 3;

    public function __construct(
        private FreePlanCatalog $catalog
    ) {}

    public function ensure(
        User $owner,
        ?User $assignedBy = null
    ): Subscription {
        if (tenancy()->initialized) {
            throw new LogicException(
                'Subscriptions require the central context.'
            );
        }

        return DB::transaction(function () use ($owner, $assignedBy): Subscription {
            User::whereKey($owner->id)
                ->lockForUpdate()
                ->firstOrFail();

            $current = $this->currentOf($owner);

            if ($current !== null) {
                if ($current->period_starts_at->isFuture()) {
                    throw new LogicException(
                        'An active subscription cannot start in the future.'
                    );
                }

                $end = $current->effectiveEndsAt();

                if ($end === null || $end->isFuture()) {
                    return $current;
                }

                $current->update([
                    'status' => StatusEnum::EXPIRED,
                    'ended_at' => $end,
                ]);
            }

            $scheduled = Subscription::where('user_id', $owner->id)
                ->where('status', StatusEnum::SCHEDULED)
                ->where('period_starts_at', '<=', now())
                ->orderBy('period_starts_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($scheduled as $next) {
                if ($next->period_ends_at !== null && $next->period_ends_at->lessThanOrEqualTo(now())) {
                    $next->update(['status' => StatusEnum::EXPIRED, 'ended_at' => $next->period_ends_at]);

                    continue;
                }

                $next->update([
                    'status' => StatusEnum::ACTIVE,
                ]);

                activity()
                    ->performedOn($next)
                    ->event('subscription_scheduled_activated')
                    ->withProperties([
                        'owner_uuid' => $owner->uuid,
                        'effective_at' => $next->period_starts_at->toISOString(),
                    ])
                    ->log('Abonnement programmé activé.');

                return $this->resolved($owner, $next, $assignedBy);
            }

            $previous = $current ?? Subscription::where('user_id', $owner->id)
                ->orderByDesc('id')->lockForUpdate()->first();

            return $this->resolved($owner, $this->createDefaultSubscription($owner, $assignedBy, $previous), $assignedBy);
        }, self::ATTEMPTS);
    }

    private function resolved(User $owner, Subscription $subscription, ?User $actor): Subscription
    {
        // Le quota doit être appliqué dans la même transaction, avant tout accès.
        Event::dispatch(self::EFFECTIVE_CHANGED, [$owner, $subscription, $actor]);

        return $subscription;
    }

    private function currentOf(User $owner): ?Subscription
    {
        return Subscription::where('user_id', $owner->id)
            ->whereIn('status', [
                StatusEnum::ACTIVE,
                StatusEnum::TRIAL,
            ])
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    private function createDefaultSubscription(
        User $owner,
        ?User $assignedBy,
        ?Subscription $previous
    ): Subscription {
        // Lire le plan gratuit directement depuis la BDD.
        $plan = $this->catalog->current();

        return Subscription::create([
            'user_id' => $owner->id,
            'plan_id' => $plan->id,
            'assigned_by_id' => $assignedBy?->id,
            'operation_key' => self::KEY_PREFIX
                .$owner->uuid.':'
                .($previous->uuid ?? self::INITIAL),
            'status' => StatusEnum::ACTIVE,
            'period' => PeriodEnum::MONTHLY,
            'agreed_amount' => $plan->monthly_price,
            'started_at' => now(),
            'period_starts_at' => now(),
            'period_ends_at' => null,
            'auto_renew' => false,
        ]);
    }
}
