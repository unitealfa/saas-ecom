<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Enums\Central\Subscriptions\TransitionModeEnum;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Billing\InvoicePaymentService;
use Brick\Math\BigDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubscriptionTransitionService
{
    public function __construct(
        private readonly AuthorizationMutation $mutations,
        private readonly DefaultSubscription $defaults,
        private readonly SubscriptionAssignmentService $assignments,
        private readonly PaidSubscriptionActivation $activation,
        private readonly InvoicePaymentService $payments,
        private readonly SubscriptionWorkflowNotifier $notifications,
    ) {}

    /** @return array<string, mixed> */
    public static function closeRules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']];
    }

    /** @return array<string, mixed> */
    public static function startNowRules(): array
    {
        return [...self::closeRules(),
            'confirmed' => ['required', 'accepted'],
            'expected_active_uuid' => ['nullable', 'uuid'],
            'expected_active_state' => ['required_with:expected_active_uuid', 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function startNow(User $actor, Subscription $subscription, array $data): void
    {
        $this->mutations->run($actor, 'startNow', fn () => $this->lock($subscription), function (Subscription $next) use ($actor, $data): void {
            $values = Validator::make($data, self::startNowRules())->validate();
            if (! hash_equals($next->transitionState(), $values['expected_state'])) {
                throw ValidationException::withMessages(['expected_state' => 'Cette période a changé. Rechargez la page.']);
            }
            $current = Subscription::where('user_id', $next->user_id)->whereKeyNot($next->id)
                ->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL])->currentlyEffective()->orderBy('id')->lockForUpdate()->get();
            $previous = $current->first();
            if ($current->count() > 1 || $previous?->uuid !== ($values['expected_active_uuid'] ?? null)
                || ($previous !== null && ! hash_equals($previous->transitionState(), $values['expected_active_state'] ?? ''))) {
                throw ValidationException::withMessages(['expected_state' => 'Votre abonnement actuel a changé. Relisez la comparaison avant de confirmer.']);
            }
            $invoice = $next->request->invoice()->lockForUpdate()->firstOrFail();
            $due = $invoice->installment()->lockForUpdate()->firstOrFail();
            $transfers = SaasPayment::withoutGlobalScope('payment')->where('document_id', $invoice->id)->orderBy('id')->lockForUpdate()->get();
            SaasCreditNote::where('original_invoice_id', $invoice->id)->orderBy('id')->lockForUpdate()->get();
            $balance = $this->payments->balance($actor, $invoice);
            $settled = $next->agreed_amount === '0.00'
                ? $next->request->getAttribute('accepted_without_payment') && $transfers->isEmpty()
                : $this->payments->confirmedPayment($actor, $invoice) !== null;
            if ($invoice->subscription_id !== $next->id || ! $due->matchesFullPeriod($next)
                || $due->installment_status !== InstallmentStatusEnum::PAID
                || ! $settled
                || ! BigDecimal::of($balance['credits_total'])->isZero()
                || ! BigDecimal::of($balance['refunds'])->isZero()
                || ! BigDecimal::of($balance['refunds_reserved'])->isZero()
                || $transfers->contains(fn (SaasPayment $payment): bool => in_array($payment->getAttribute('transfer_status'),
                    [TransferStatusEnum::DECLARED, TransferStatusEnum::APPROVED, TransferStatusEnum::UNCERTAIN], true))) {
                throw ValidationException::withMessages(['subscription' => 'Cette période exige un paiement intégral confirmé, sans correction ni remboursement à examiner.']);
            }
            $start = now()->toImmutable()->startOfSecond();
            $end = $next->period === PeriodEnum::MONTHLY ? $start->addMonthNoOverflow() : $start->addYearNoOverflow();
            $next->forceFill(['effective_starts_at' => $start, 'effective_ends_at' => $end,
                'transition_mode' => TransitionModeEnum::IMMEDIATE, 'replaces_subscription_id' => $previous?->id])->save();
            $this->activation->activatePaidSubscription($actor, $next, $due);
            $this->notifications->activated($next);
            activity()->causedBy($actor)->performedOn($next)->event('subscription_scheduled_started_early')
                ->withProperties(['previous_subscription_uuid' => $previous?->uuid,
                    'effective_starts_at' => $start->toISOString(), 'effective_ends_at' => $end->toISOString()])
                ->log('Période payée démarrée immédiatement après confirmation ; documents et dates de facturation conservés.');
        });
    }

    /** @return array<string, mixed> */
    public static function changeRules(): array
    {
        $rules = SubscriptionAssignmentService::rules();
        unset($rules['owner_uuid']);

        return [...$rules, ...self::closeRules()];
    }

    /** @param array<string, mixed> $data */
    public function close(User $actor, Subscription $subscription, array $data): void
    {
        $this->mutations->run($actor, 'close', fn () => $this->lock($subscription),
            function (Subscription $current) use ($actor, $data): void {
                $values = Validator::make($data, self::closeRules())->validate();
                $this->assertCurrent($current, $values['expected_state']);
                $this->assertNoScheduled($current);
                $before = $current->only(['auto_renew', 'ended_at']);
                $current->forceFill(['auto_renew' => false, 'ended_at' => $current->effectiveEndsAt()])->save();
                activity()->causedBy($actor)->performedOn($current)->event('subscription_closure_scheduled')
                    ->withProperties(['before' => $before, 'effective_at' => $current->effectiveEndsAt()->toISOString()])
                    ->log('Fin de l’abonnement programmée.');
            });
    }

    /** @param array<string, mixed> $data */
    public function deactivate(User $actor, Subscription $subscription, array $data): void
    {
        $this->mutations->run($actor, 'deactivate', fn () => $this->lock($subscription),
            function (Subscription $current) use ($actor, $data): void {
                $values = Validator::make($data, self::closeRules())->validate();
                if (! hash_equals($current->transitionState(), $values['expected_state'])) {
                    throw ValidationException::withMessages(['expected_state' => 'L’abonnement a changé. Rechargez la page.']);
                }
                $current->load('plan');
                $end = $current->effectiveEndsAt();
                if (! in_array($current->status, [StatusEnum::ACTIVE, StatusEnum::TRIAL], true)
                    || $current->effectiveStartsAt()->isFuture() || $current->isFree()
                    || ($end !== null && ! $end->isFuture())) {
                    throw ValidationException::withMessages(['subscription' => 'Cet abonnement ne peut pas être désactivé.']);
                }
                $this->assertNoScheduled($current);
                $endedAt = now();
                $current->forceFill(['status' => StatusEnum::CANCELLED, 'ended_at' => $endedAt, 'auto_renew' => false])->save();
                $this->defaults->ensure($current->owner, $actor);
                activity()->causedBy($actor)->performedOn($current)->event('subscription_deactivated')
                    ->withProperties(['owner_uuid' => $current->owner->uuid, 'effective_at' => $endedAt->toISOString()])
                    ->log('Abonnement désactivé immédiatement.');
            });
    }

    /** @param array<string, mixed> $data */
    public function change(User $actor, Subscription $subscription, array $data, ?UploadedFile $proof = null): Subscription
    {
        $values = Validator::make([...$data, 'proof' => $proof], self::changeRules())->validate();
        Gate::forUser($actor)->authorize('change', $subscription);

        return $this->assignments->assign($actor, [
            ...$values, 'owner_uuid' => $subscription->owner->uuid,
            'source_uuid' => $subscription->uuid, 'expected_source_state' => $values['expected_state'],
        ], $proof);
    }

    private function lock(Subscription $subscription): Subscription
    {
        User::whereKey($subscription->user_id)->lockForUpdate()->firstOrFail();

        return Subscription::whereKey($subscription->id)->where('uuid', $subscription->uuid)->lockForUpdate()->firstOrFail();
    }

    private function assertCurrent(Subscription $current, string $expected): void
    {
        if (! hash_equals($current->transitionState(), $expected)) {
            throw ValidationException::withMessages(['expected_state' => 'L’abonnement a changé. Rechargez la page.']);
        }
        $current->load('plan');
        if ($current->status !== StatusEnum::ACTIVE || $current->effectiveStartsAt()->isFuture() || $current->isFree()
            || $current->effectiveEndsAt() === null || ! $current->effectiveEndsAt()->isFuture() || $current->ended_at !== null) {
            throw ValidationException::withMessages(['subscription' => 'Cet abonnement ne peut pas être modifié par cette action.']);
        }
    }

    private function assertNoScheduled(Subscription $current): void
    {
        if (Subscription::where('user_id', $current->user_id)->where('status', StatusEnum::SCHEDULED)->lockForUpdate()->exists()) {
            throw ValidationException::withMessages(['subscription' => 'Un changement est déjà programmé pour ce commerçant.']);
        }
    }
}
