<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Tenants\TenantStatusService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;

class QuotaService
{
    public function __construct(
        private readonly AuthorizationMutation $mutations,
        private readonly DefaultSubscription $subscriptions,
        private readonly TenantStatusService $tenants,
        private readonly CentralUserAccess $access,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(User $actor, User $owner): array
    {
        Gate::forUser($actor)->authorize('viewQuota', $owner);
        $subscription = $this->current($owner);
        $expired = $subscription === null || $subscription->period_starts_at->isFuture()
            || ($subscription->effectiveEndsAt() !== null && ! $subscription->effectiveEndsAt()->isFuture());
        $grant = $expired ? null : PlanFeature::query()->where('plan_id', $subscription->plan_id)
            ->whereHas('feature', fn ($query) => $query->where('code', Feature::SHOPS_CODE))->first();

        return [
            'subscription' => $subscription?->load('plan'),
            'pending' => $expired,
            'grant' => $grant,
            'features' => $expired ? collect() : PlanFeature::query()->where('plan_id', $subscription->plan_id)->with('feature')->get(),
            'shops' => Tenant::withTrashed()->where('user_id', $owner->id)->orderBy('id')->get(),
            'usage' => DB::table('feature_usage')->join('features', 'features.id', '=', 'feature_usage.feature_id')
                ->leftJoin('tenants', 'tenants.id', '=', 'feature_usage.tenant_id')
                ->where('feature_usage.user_id', $owner->id)
                ->select('features.name', 'features.code', 'tenants.shop_name', 'feature_usage.quantity', 'feature_usage.period_starts_at', 'feature_usage.period_ends_at')
                ->orderByDesc('feature_usage.period_starts_at')->paginate(20),
            'state' => $this->state($owner),
        ];
    }

    public function reconcile(User $actor, User $owner, string $expected): void
    {
        $this->mutations->run($actor, 'reconcileQuota', fn () => User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail(),
            function (User $locked) use ($actor, $expected): void {
                Tenant::where('user_id', $locked->id)->orderBy('id')->lockForUpdate()->get();
                if (! hash_equals($this->state($locked), $expected)) {
                    throw ValidationException::withMessages(['expected_state' => 'Les quotas ont changé. Rechargez la page.']);
                }
                if ($locked->status !== UserStatus::ACTIVE || $this->access->isStaff($locked)
                    || $locked->email_verified_at === null || $locked->legal_verification_status !== VerificationStatusEnum::VERIFIED
                    || $locked->legal_verified_at === null || $locked->legal_profile_version === null) {
                    throw ValidationException::withMessages(['user' => 'Le propriétaire doit être un commerçant actif dont l’e-mail et le dossier sont vérifiés.']);
                }
                $previous = $this->current($locked);
                $subscription = $this->subscriptions->ensure($locked, $actor);
                if ($previous?->id === $subscription->id) {
                    $this->tenants->applySubscriptionQuota($locked, $subscription, $actor);
                }
                activity()->causedBy($actor)->performedOn($locked)->event('quota_reconciled')
                    ->withProperties(['subscription_uuid' => $subscription->uuid])->log('Réconciliation des quotas demandée.');
            });
    }

    /** @return LengthAwarePaginator<int, Activity> */
    public function history(User $actor, User $owner): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewQuotaHistory', $owner);
        $shops = Tenant::withTrashed()->where('user_id', $owner->id)->select('id');
        $subscriptions = Subscription::where('user_id', $owner->id)->select('id');

        return Activity::query()->where(function ($query) use ($owner, $shops, $subscriptions): void {
            $query->where(fn ($q) => $q->where('subject_type', $owner->getMorphClass())->where('subject_id', $owner->id)->where('event', 'quota_reconciled'))
                ->orWhere(fn ($q) => $q->where('subject_type', (new Tenant)->getMorphClass())->whereIn('subject_id', $shops)
                    ->whereIn('event', ['tenant_quota_rebalanced', 'tenant_subscription_access_blocked', 'tenant_priorities_updated', 'tenant_archive']))
                ->orWhere(fn ($q) => $q->where('subject_type', (new Subscription)->getMorphClass())->whereIn('subject_id', $subscriptions)
                    ->whereIn('event', ['subscription_assigned', 'subscription_deactivated', 'subscription_closure_scheduled', 'subscription_change_scheduled', 'subscription_scheduled_activated']));
        })->with('causer')->orderByDesc('id')->paginate(20);
    }

    private function current(User $owner): ?Subscription
    {
        return Subscription::where('user_id', $owner->id)->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL])->orderByDesc('id')->first();
    }

    private function state(User $owner): string
    {
        $subscriptions = Subscription::where('user_id', $owner->id)->orderBy('id')->get();
        $shops = Tenant::withTrashed()->where('user_id', $owner->id)->orderBy('id')->get(['id', 'status', 'activation_priority', 'updated_at', 'deleted_at']);

        return hash('sha256', json_encode([$subscriptions->map(fn (Subscription $subscription) => $subscription->transitionState())->all(), $shops->toArray()], JSON_THROW_ON_ERROR));
    }
}
