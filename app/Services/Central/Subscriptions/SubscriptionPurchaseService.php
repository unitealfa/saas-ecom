<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionPurchaseService
{
    public const string REQUESTED = 'subscription_purchase_requested';

    public const string ASSIGNED = 'subscription_assigned';

    public const string EXPIRED = 'subscription_scheduled_expired';

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['plan_uuid' => ['required', 'uuid:4'], 'operation_id' => ['required', 'uuid:4'],
            'period' => ['required', 'integer', Rule::enum(PeriodEnum::class)],
            'expected_amount' => ['required', 'string', 'regex:/^\d{1,12}\.\d{2}$/D'],
            ...array_fill_keys(['user_id', 'owner_uuid', 'status', 'agreed_amount', 'started_at', 'period_starts_at', 'period_ends_at', 'auto_renew', 'assigned_by_id', 'tenant_id'], ['missing'])];
    }

    /** @param array<string, mixed> $data */
    public function choose(User $owner, array $data): Subscription
    {
        return $this->chooseFor($owner, $owner, $data);
    }

    /** @param array<string, mixed> $data */
    public function chooseFor(User $actor, User $owner, array $data): Subscription
    {
        abort_if(tenancy()->initialized, 403);

        try {
            return $this->mutations->transaction(function () use ($actor, $owner, $data): Subscription {
                $owner = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('purchaseFor', [Subscription::class, $owner]);
                $values = Validator::make($data, self::rules())->validate();
                $administrative = $actor->id !== $owner->id;
                $key = $administrative ? 'assign:'.strtolower($values['operation_id']) : 'purchase:'.$owner->uuid.':'.strtolower($values['operation_id']);
                $existing = Subscription::where('operation_key', $key)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ($existing->user_id !== $owner->id || $existing->plan->uuid !== strtolower($values['plan_uuid']) || $existing->period->value !== (int) $values['period']
                        || $existing->agreed_amount !== $values['expected_amount']) {
                        $this->fail('Cette demande a déjà été utilisée avec une autre offre ou un autre prix.');
                    }

                    return $existing;
                }
                $subscriptions = Subscription::where('user_id', $owner->id)->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL, StatusEnum::SCHEDULED])
                    ->with('plan')->orderBy('id')->lockForUpdate()->get();
                foreach ($subscriptions as $subscription) {
                    if ($subscription->status === StatusEnum::SCHEDULED && $subscription->period_ends_at?->lessThanOrEqualTo(now())) {
                        $subscription->forceFill(['status' => StatusEnum::EXPIRED, 'ended_at' => $subscription->period_ends_at])->save();
                        activity('central')->causedBy($actor)->performedOn($subscription)->event(self::EXPIRED)
                            ->log('Période prévue arrivée à expiration ; obligation et justificatifs conservés.');
                    }
                }
                if ($subscriptions->contains(fn (Subscription $subscription): bool => $subscription->status === StatusEnum::SCHEDULED)) {
                    $this->fail('Une période est déjà prévue ou en attente de paiement. Consultez cet abonnement avant d’en choisir un autre.');
                }
                $plan = Plan::where('uuid', strtolower($values['plan_uuid']))->active()->lockForUpdate()->first();
                if ($plan === null || $plan->code === 'free') {
                    $this->fail('Choisissez une offre payante actuellement disponible. L’offre gratuite ne nécessite aucun paiement.');
                }
                $period = PeriodEnum::from((int) $values['period']);
                $amount = $period === PeriodEnum::MONTHLY ? $plan->monthly_price : $plan->annual_price;
                if ($amount !== $values['expected_amount'] || BigDecimal::of($amount)->isLessThanOrEqualTo(0)
                    || BigDecimal::of($amount)->isGreaterThan('999999.99')) {
                    $this->fail('Le prix a changé ou n’est pas payable pour cette période. Rechargez l’offre.');
                }
                $start = now()->toImmutable()->startOfSecond();
                foreach ($subscriptions as $current) {
                    if ($current->plan->code === 'free') {
                        continue;
                    }
                    $end = $current->effectiveEndsAt();
                    if ($end === null) {
                        $this->fail('Un abonnement sans date de fin doit être examiné avant de prévoir une nouvelle période.');
                    }
                    if ($end->greaterThan($start)) {
                        if ($administrative) {
                            throw ValidationException::withMessages(['owner_uuid' => 'Ce commerçant possède déjà un abonnement payant en cours. Utilisez le changement d’offre à échéance.']);
                        }
                        $start = $end;
                    }
                }
                $end = $period === PeriodEnum::MONTHLY ? $start->addMonthNoOverflow() : $start->addYearNoOverflow();
                $subscription = new Subscription;
                $subscription->forceFill(['user_id' => $owner->id, 'plan_id' => $plan->getKey(), 'operation_key' => $key,
                    'status' => StatusEnum::SCHEDULED, 'period' => $period, 'agreed_amount' => $amount,
                    'assigned_by_id' => $administrative ? $actor->id : null,
                    'started_at' => $start, 'period_starts_at' => $start, 'period_ends_at' => $end, 'auto_renew' => false])->save();
                (new SubscriptionInstallment)->forceFill(['user_id' => $owner->id, 'subscription_id' => $subscription->getKey(),
                    'operation_key' => 'installment:'.$subscription->uuid.':full-period', 'installment_number' => 'ECH-'.Str::uuid(),
                    'installment_amount' => $amount, 'installment_status' => InstallmentStatusEnum::PENDING,
                    'period_starts_at' => $start, 'period_ends_at' => $end, 'due_at' => $start])->save();
                activity('central')->causedBy($actor)->performedOn($subscription)->event($administrative ? self::ASSIGNED : self::REQUESTED)
                    ->withProperties(['subscription_uuid' => $subscription->uuid, 'amount' => $amount])
                    ->log('Offre choisie et période intégrale réservée, sans activation avant paiement.');

                return $subscription;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'operation_key') && ! str_contains($exception->getMessage(), 'uq_subscriptions_f59d836b7a')) {
                throw $exception;
            }
            $this->fail('Une attribution concurrente a déjà utilisé cette demande. Rechargez avant de recommencer.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['plan_uuid' => $message]);
    }
}
