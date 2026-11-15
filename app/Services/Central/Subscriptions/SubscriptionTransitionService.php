<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubscriptionTransitionService
{
    public function __construct(private readonly AuthorizationMutation $mutations, private readonly DefaultSubscription $defaults, private readonly SubscriptionAssignmentService $assignments) {}

    /** @return array<string, mixed> */
    public static function closeRules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']];
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
