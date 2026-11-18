<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Enums\Central\Subscriptions\TransitionModeEnum;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use App\Services\Central\Tenants\TenantStatusService;
use Illuminate\Validation\ValidationException;

class PaidSubscriptionActivation
{
    public const string ACTIVATED = 'subscription_payment_activated';

    public function __construct(private readonly TenantStatusService $tenants) {}

    /** Appel sous les verrous propriétaire, abonnement, échéance et facture. */
    public function activatePaidSubscription(User $actor, Subscription $subscription, SubscriptionInstallment $due): void
    {
        if (! $due->matchesFullPeriod($subscription) || $subscription->status === StatusEnum::CANCELLED
            || $subscription->period_ends_at === null || $subscription->period_ends_at->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['payment' => 'Cette période est expirée, annulée ou ne correspond plus à l’abonnement. Aucun droit modifié.']);
        }
        if ($due->installment_status !== InstallmentStatusEnum::PAID) {
            $due->forceFill(['installment_status' => InstallmentStatusEnum::PAID])->save();
        }
        if ($subscription->effectiveStartsAt()->isFuture()) {
            return;
        }
        $others = Subscription::where('user_id', $subscription->user_id)->whereKeyNot($subscription->id)
            ->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL])->with('plan')->orderBy('id')->lockForUpdate()->get();
        foreach ($others as $current) {
            $end = $current->effectiveEndsAt();
            $replacement = $subscription->transition_mode === TransitionModeEnum::IMMEDIATE
                && $subscription->getAttribute('replaces_subscription_id') === $current->id;
            if (! $current->isFree() && ($end === null || $end->isFuture()) && ! $replacement) {
                throw ValidationException::withMessages(['payment' => 'Une autre période payante est encore active. Préservez ses droits et vérifiez les dates avant de confirmer.']);
            }
            $current->forceFill(['status' => $end !== null && $end->lessThanOrEqualTo(now()) ? StatusEnum::EXPIRED : StatusEnum::CANCELLED,
                'ended_at' => $end !== null && $end->lessThanOrEqualTo(now()) ? $end : now(), 'auto_renew' => false])->save();
        }
        $subscription->forceFill(['status' => StatusEnum::ACTIVE])->save();
        $this->tenants->applySubscriptionQuota($subscription->owner, $subscription, $actor);
        activity('central')->causedBy($actor)->performedOn($subscription)->event(self::ACTIVATED)
            ->withProperties(['subscription_uuid' => $subscription->uuid, 'installment_uuid' => $due->uuid])
            ->log('Période intégralement payée activée une seule fois.');
    }
}
