<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Tenants\TenantStatusService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionAssignmentService
{
    public function __construct(
        private readonly AuthorizationMutation $mutations,
        private readonly CentralUserAccess $access,
        private readonly TenantStatusService $tenants,
    ) {}

    public static function rules(): array
    {
        return [
            'owner_uuid' => ['required', 'string', 'uuid:4'],
            'plan_uuid' => ['required', 'string', 'uuid:4'],
            'operation_key' => ['required', 'string', 'uuid:4'],
            'period' => ['required', 'integer', Rule::enum(PeriodEnum::class)],
            'expected_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            ...array_fill_keys(['id', 'uuid', 'status', 'agreed_amount', 'period_starts_at', 'period_ends_at', 'auto_renew', 'assigned_by_id', 'tenant_id'], ['prohibited']),
        ];
    }

    public function assign(User $actor, array $data): Subscription
    {
        return $this->mutations->run($actor, 'assign', fn () => Subscription::class,
            function () use ($actor, $data): Subscription {
                $values = Validator::make($data, self::rules())->validate();
                $owner = User::where('uuid', $values['owner_uuid'])->lockForUpdate()->first();
                if ($owner === null || $owner->status !== UserStatus::ACTIVE || $this->access->isStaff($owner)) {
                    throw ValidationException::withMessages(['owner_uuid' => 'Choisissez un commerçant actif.']);
                }
                $key = 'assign:'.strtolower($values['operation_key']);
                $existing = Subscription::where('operation_key', $key)->lockForUpdate()->first();
                if ($existing !== null) {
                    $existing->load('plan');
                    if ($existing->user_id !== $owner->id || $existing->plan?->uuid !== strtolower($values['plan_uuid'])
                        || $existing->period->value !== (int) $values['period']
                        || $existing->agreed_amount !== number_format((float) $values['expected_amount'], 2, '.', '')) {
                        throw ValidationException::withMessages(['operation_key' => 'Cette demande a déjà été utilisée avec d’autres données.']);
                    }

                    return $existing;
                }
                $plan = Plan::where('uuid', $values['plan_uuid'])->lockForUpdate()->first();
                if ($plan === null || ! $plan->is_active || strtolower($plan->code) === 'free') {
                    throw ValidationException::withMessages(['plan_uuid' => 'Choisissez une offre commercialisée.']);
                }
                $period = PeriodEnum::from((int) $values['period']);
                $amount = $period === PeriodEnum::MONTHLY ? $plan->monthly_price : $plan->annual_price;
                if ((float) $amount > 999999.99) {
                    throw ValidationException::withMessages(['plan_uuid' => 'Le prix de cette offre dépasse la capacité des abonnements.']);
                }
                if (number_format((float) $values['expected_amount'], 2, '.', '') !== $amount) {
                    throw ValidationException::withMessages(['plan_uuid' => 'Le tarif a changé. Rechargez le formulaire avant de recommencer.']);
                }
                $now = now()->startOfSecond();
                $current = Subscription::where('user_id', $owner->id)
                    ->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL, StatusEnum::SCHEDULED])
                    ->with('plan')->orderBy('id')->lockForUpdate()->get();
                foreach ($current as $previous) {
                    if ($previous->status !== StatusEnum::ACTIVE || ($previous->plan?->code !== 'free' && ($previous->period_ends_at === null || $previous->period_ends_at->isFuture()))) {
                        throw ValidationException::withMessages(['owner_uuid' => 'Ce commerçant possède déjà un abonnement en cours ou prévu.']);
                    }
                }
                foreach ($current as $previous) {
                    $expired = $previous->period_ends_at !== null && $previous->period_ends_at->lessThanOrEqualTo($now);
                    $previous->forceFill(['status' => $expired ? StatusEnum::EXPIRED : StatusEnum::CANCELLED, 'ended_at' => $expired ? $previous->period_ends_at : $now, 'auto_renew' => false])->save();
                }
                try {
                    $subscription = Subscription::create([
                        'user_id' => $owner->id, 'plan_id' => $plan->id, 'assigned_by_id' => $actor->id,
                        'operation_key' => $key, 'status' => StatusEnum::ACTIVE, 'period' => $period,
                        'agreed_amount' => $amount, 'started_at' => $now, 'period_starts_at' => $now,
                        'period_ends_at' => $period === PeriodEnum::MONTHLY ? $now->copy()->addMonthNoOverflow() : $now->copy()->addYearNoOverflow(),
                        'auto_renew' => false,
                    ]);
                } catch (UniqueConstraintViolationException $exception) {
                    if (str_contains($exception->getMessage(), 'operation_key') || str_contains($exception->getMessage(), 'uq_subscriptions_f59d836b7a')) {
                        throw ValidationException::withMessages(['operation_key' => 'Une attribution concurrente a été enregistrée. Rechargez avant de recommencer.']);
                    }
                    throw $exception;
                }
                $this->tenants->rebalanceForSubscription($actor, $owner);
                activity()->causedBy($actor)->performedOn($subscription)->event('subscription_assigned')
                    ->withProperties(['owner_uuid' => $owner->uuid, 'plan_uuid' => $plan->uuid, 'period' => $period->value,
                        'agreed_amount' => $amount, 'period_starts_at' => $now->toISOString(), 'period_ends_at' => $subscription->period_ends_at->toISOString(),
                        'previous_uuids' => $current->pluck('uuid')->all()])
                    ->log('Abonnement attribué.');

                return $subscription;
            });
    }
}
