<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use App\Services\Central\Plans\FreePlanCatalog;
use Illuminate\Database\Eloquent\Builder;
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
                    $paidNext = $current->plan->code === 'free' && $this->paidDocuments()->where('user_id', $owner->id)
                        ->whereHas('subscription', fn (Builder $query) => $query->where('status', StatusEnum::SCHEDULED)
                            ->where('period_starts_at', '<=', now())->where('period_ends_at', '>', now()))->exists();
                    if (! $paidNext) {
                        return $current;
                    }
                    $current->forceFill(['status' => StatusEnum::CANCELLED, 'ended_at' => now()])->save();
                } else {
                    $current->update([
                        'status' => StatusEnum::EXPIRED,
                        'ended_at' => $end,
                    ]);
                }
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
                $obligation = SubscriptionInstallment::where('parent_subscription_id', $next->id)
                    ->where('period_starts_at', $next->period_starts_at)->where('period_ends_at', $next->period_ends_at)->exists();
                if ($obligation && ! $this->paidDocuments()->where('subscription_id', $next->id)
                    ->where('period_starts_at', $next->period_starts_at)->where('period_ends_at', $next->period_ends_at)->exists()) {
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

    /** @return Builder<SaasInvoice> */
    private function paidDocuments(): Builder
    {
        return SaasInvoice::where('status', DocumentStatusEnum::ISSUED)
            ->whereHas('payments', fn (Builder $query) => $query->where('transfer_status', TransferStatusEnum::VERIFIED), '=', 1)
            ->whereHas('installment', fn (Builder $query) => $query->where('installment_status', InstallmentStatusEnum::PAID))
            ->whereHas('payments', fn (Builder $query) => $query->where('transfer_status', TransferStatusEnum::VERIFIED)
                ->whereNull('reversal_of_id')->whereRaw('saas_transfers.amount >= saas_invoices.total_amount - (SELECT COALESCE(SUM(c.total_amount),0) FROM saas_invoices c WHERE c.original_invoice_id=saas_invoices.id AND c.document_type=? AND c.status=?)', [DocumentTypeEnum::CREDIT_NOTE->value, DocumentStatusEnum::ISSUED->value])
                ->whereColumn('saas_transfers.currency', 'saas_invoices.currency'));
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
