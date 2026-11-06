<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionTransitionService
{
    public function __construct(private readonly AuthorizationMutation $mutations, private readonly DefaultSubscription $defaults) {}

    /** @return array<string, mixed> */
    public static function closeRules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']];
    }

    /** @return array<string, mixed> */
    public static function changeRules(): array
    {
        return [
            ...self::closeRules(),
            'plan_uuid' => ['required', 'string', 'uuid:4'],
            'period' => ['required', 'integer', Rule::enum(PeriodEnum::class)],
            'expected_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'operation_key' => ['required', 'string', 'uuid:4'],
        ];
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
                $current->forceFill(['auto_renew' => false, 'ended_at' => $current->period_ends_at])->save();
                activity()->causedBy($actor)->performedOn($current)->event('subscription_closure_scheduled')
                    ->withProperties(['before' => $before, 'effective_at' => $current->period_ends_at->toISOString()])
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
                    || $current->period_starts_at->isFuture() || $current->plan?->code === 'free'
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
    public function change(User $actor, Subscription $subscription, array $data): Subscription
    {
        return $this->mutations->run($actor, 'change', fn () => $this->lock($subscription),
            function (Subscription $current) use ($actor, $data): Subscription {
                $values = Validator::make($data, self::changeRules())->validate();
                $key = 'change:'.$current->uuid.':'.strtolower($values['operation_key']);
                $replay = Subscription::where('operation_key', $key)->lockForUpdate()->first();
                if ($replay !== null) {
                    $replay->load('plan');
                    if ($replay->user_id !== $current->user_id || $replay->plan?->uuid !== strtolower($values['plan_uuid'])
                        || $replay->period->value !== (int) $values['period']
                        || $replay->agreed_amount !== (string) BigDecimal::of((string) $values['expected_amount'])->toScale(2)) {
                        throw ValidationException::withMessages(['operation_key' => 'Cette demande a déjà été utilisée avec d’autres données.']);
                    }

                    return $replay;
                }
                $this->assertCurrent($current, $values['expected_state']);
                $this->assertNoScheduled($current);
                $plan = Plan::where('uuid', $values['plan_uuid'])->lockForUpdate()->first();
                if ($plan === null || ! $plan->is_active || strtolower($plan->code) === 'free' || $plan->id === $current->plan_id) {
                    throw ValidationException::withMessages(['plan_uuid' => 'Choisissez une autre offre commercialisée.']);
                }
                if (Tenant::where('user_id', $current->user_id)->exists()) {
                    $shops = Feature::where('code', Feature::SHOPS_CODE)->lockForUpdate()->first();
                    $grant = $shops === null ? null : PlanFeature::where('plan_id', $plan->id)->where('feature_id', $shops->id)->lockForUpdate()->first();
                    if ($shops === null || ! $shops->is_active || $grant === null || ! $grant->is_active) {
                        throw ValidationException::withMessages(['plan_uuid' => 'Cette offre doit permettre les boutiques du commerçant.']);
                    }
                }
                $period = PeriodEnum::from((int) $values['period']);
                $amount = $period === PeriodEnum::MONTHLY ? $plan->monthly_price : $plan->annual_price;
                if (BigDecimal::of($amount)->isGreaterThan('999999.99')
                    || (string) BigDecimal::of((string) $values['expected_amount'])->toScale(2) !== $amount) {
                    throw ValidationException::withMessages(['plan_uuid' => 'Le tarif a changé ou dépasse la capacité des abonnements. Rechargez le formulaire.']);
                }
                $start = $current->period_ends_at;
                $next = Subscription::create([
                    'user_id' => $current->user_id, 'plan_id' => $plan->id, 'assigned_by_id' => $actor->id,
                    'operation_key' => $key, 'status' => StatusEnum::SCHEDULED, 'period' => $period,
                    'agreed_amount' => $amount, 'started_at' => $start, 'period_starts_at' => $start,
                    'period_ends_at' => $period === PeriodEnum::MONTHLY ? $start->addMonthNoOverflow() : $start->addYearNoOverflow(),
                    'auto_renew' => false,
                ]);
                (new SubscriptionInstallment)->forceFill([
                    'user_id' => $current->user_id, 'parent_subscription_id' => $next->id,
                    'operation_key' => 'installment:'.$next->uuid.':full-period', 'installment_number' => 'ECH-'.Str::uuid(),
                    'installment_amount' => $amount, 'installment_status' => InstallmentStatusEnum::PENDING,
                    'period_starts_at' => $next->period_starts_at, 'period_ends_at' => $next->period_ends_at, 'due_at' => $next->period_starts_at,
                ])->save();
                $current->forceFill(['auto_renew' => false, 'ended_at' => $start])->save();
                activity()->causedBy($actor)->performedOn($current)->event('subscription_change_scheduled')
                    ->withProperties(['next_uuid' => $next->uuid, 'plan_uuid' => $plan->uuid, 'effective_at' => $start->toISOString(), 'agreed_amount' => $amount])
                    ->log('Changement d’offre programmé.');

                return $next;
            });
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
        if ($current->status !== StatusEnum::ACTIVE || $current->period_starts_at->isFuture() || $current->plan?->code === 'free'
            || $current->period_ends_at === null || ! $current->period_ends_at->isFuture() || $current->ended_at !== null) {
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
