<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\RequestStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use App\Services\Central\Plans\FreePlanCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class DefaultSubscription
{
    public const string EFFECTIVE_CHANGED = 'central.subscription.effective_changed';

    private const string KEY_PREFIX = 'default-free:';

    private const string INITIAL = 'initial';

    private const int ATTEMPTS = 3;

    public function __construct(
        private FreePlanCatalog $catalog,
        private readonly SubscriptionWorkflowNotifier $notifications,
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
                if ($current->effectiveStartsAt()->isFuture()) {
                    throw new LogicException(
                        'An active subscription cannot start in the future.'
                    );
                }

                $end = $current->effectiveEndsAt();

                if ($end === null || $end->isFuture()) {
                    $paidNext = $current->isFree() && $this->settledDocuments()->where('user_id', $owner->id)
                        ->whereHas('subscription', fn (Builder $query) => $query->where('status', StatusEnum::SCHEDULED)
                            ->where('period_starts_at', '<=', now())->where('period_ends_at', '>', now()))->exists();
                    if (! $current->isFree() || (! $paidNext && $current->plan_id === $this->catalog->current()->id)) {
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
                $obligation = SubscriptionInstallment::where('subscription_id', $next->id)
                    ->where('period_starts_at', $next->period_starts_at)->where('period_ends_at', $next->period_ends_at)->exists();
                if ($obligation && ! $this->settledDocuments()->where('subscription_id', $next->id)
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
                $this->notifications->activated($next);

                return $this->resolved($owner, $next, $assignedBy);
            }

            $previous = $current ?? Subscription::where('user_id', $owner->id)
                ->orderByDesc('id')->lockForUpdate()->first();

            return $this->resolved($owner, $this->createDefaultSubscription($owner, $assignedBy, $previous), $assignedBy);
        }, self::ATTEMPTS);
    }

    public function switchToDefault(User $owner, Subscription $source, string $expected, string $planUuid, string $planState): Subscription
    {
        Gate::forUser($owner)->authorize('purchase', Subscription::class);
        abort_unless($source->user_id === $owner->id, 403);

        return DB::transaction(function () use ($owner, $source, $expected, $planUuid, $planState): Subscription {
            $owner = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('purchase', Subscription::class);
            $current = Subscription::whereKey($source->id)->where('user_id', $owner->id)
                ->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL])->currentlyEffective()->lockForUpdate()->first();
            $plan = Plan::where('uuid', $planUuid)->where('is_default', true)->active()->lockForUpdate()->first();
            if ($current === null || $plan === null || $this->catalog->current()->id !== $plan->id
                || ! hash_equals($current->transitionState(), $expected)
                || ! hash_equals($plan->compositionState($plan->features()->lockForUpdate()->get()), $planState)
                || ($current->isFree() && $current->plan_id === $plan->id)) {
                throw ValidationException::withMessages(['subscription' => 'Les offres ont changé. Relisez la comparaison avant de confirmer.']);
            }
            if (Subscription::where('user_id', $owner->id)->whereKeyNot($current->id)
                ->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL])->currentlyEffective()->lockForUpdate()->exists()
                || Subscription::where('user_id', $owner->id)->where('status', StatusEnum::SCHEDULED)
                    ->where('period_ends_at', '>', now())->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['subscription' => 'Une autre période est en cours ou prévue. Consultez-la avant de changer d’offre.']);
            }
            $current->forceFill(['status' => StatusEnum::CANCELLED, 'ended_at' => now(), 'auto_renew' => false])->save();
            $next = $this->createDefaultSubscription($owner, null, $current, $plan);
            activity('central')->causedBy($owner)->performedOn($next)->event('subscription_default_selected')
                ->withProperties(['previous_subscription_uuid' => $current->uuid])->log('Passage au plan par défaut confirmé par le commerçant.');

            return $this->resolved($owner, $next, $owner);
        }, self::ATTEMPTS);
    }

    /** @return Builder<SaasInvoice> */
    private function settledDocuments(): Builder
    {
        return SaasInvoice::where('status', DocumentStatusEnum::ISSUED)
            ->where(fn (Builder $query) => $query->whereIn('id', $this->paidDocuments()->select('id'))
                ->orWhere(fn (Builder $query) => $query->where('total_amount', '0.00')
                    ->whereHas('subscription.request', fn (Builder $request) => $request
                        ->where('status', RequestStatusEnum::ACCEPTED)->where('accepted_without_payment', true)
                        ->whereColumn('subscription_requests.current_invoice_id', 'saas_invoices.id')
                        ->whereColumn('subscription_requests.current_subscription_id', 'saas_invoices.subscription_id'))));
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
            ->whereHas('subscription', fn (Builder $query) => $query->whereNull('subscription_request_id')
                ->orWhereHas('request', fn (Builder $request) => $request->where('status', RequestStatusEnum::ACCEPTED)))
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
        ?Subscription $previous,
        ?Plan $plan = null,
    ): Subscription {
        // Lire le plan gratuit directement depuis la BDD.
        $plan ??= $this->catalog->current();

        return Subscription::create([
            'user_id' => $owner->id,
            'plan_id' => $plan->id,
            'assigned_by_id' => $assignedBy?->id,
            'operation_key' => self::KEY_PREFIX
                .$owner->uuid.':'
                .($previous->uuid ?? self::INITIAL),
            'status' => StatusEnum::ACTIVE,
            'period' => PeriodEnum::MONTHLY,
            'agreed_amount' => '0.00',
            'started_at' => now(),
            'period_starts_at' => now(),
            'period_ends_at' => null,
            'auto_renew' => false,
        ]);
    }
}
