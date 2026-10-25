<?php

namespace App\Services\Central\Auth;

use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Central\Users\JourneyState;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Activity;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/** @phpstan-type ShopProgress array{name: string, status: string, prepared: bool, failed: bool, ready: bool, retry_url: string|null} */
class RegistrationJourney
{
    private const string EVENT_APPROVED = 'central_account_approved';

    private const string EVENT_REJECTED = 'central_account_rejected';

    public function __construct(private CentralUserAccess $access) {}

    public function mayAuthenticate(User $user): bool
    {
        return $user->status === StatusEnum::ACTIVE
            || (in_array($user->status, [StatusEnum::PENDING, StatusEnum::REJECTED], true) && ! $this->access->isStaff($user));
    }

    public function destination(User $user): string
    {
        return route($this->state($user)->routeName());
    }

    public function state(User $user): JourneyState
    {
        return $this->accountState($user) ?? $this->stateFromTenants($this->loadTenants($user));
    }

    /** @return array{state: string, redirect: string, reason: string|null, shops: array<int, ShopProgress>, version: string} */
    public function progress(User $user): array
    {
        $shops = [];
        $state = $this->accountState($user);

        if ($state === null) {
            $tenants = $this->loadTenants($user);
            $state = $this->stateFromTenants($tenants);
            $shops = $tenants->map(fn (Tenant $tenant): array => $this->shopData($tenant, $user))->all();
        }

        $reason = $state === JourneyState::REJECTED ? $this->refusalReason($user) : null;

        return [
            'state' => $state->value,
            'redirect' => route($state->routeName()),
            'reason' => $reason,
            'shops' => $shops,
            'version' => hash('sha256', json_encode([$state->value, $reason, $shops], JSON_THROW_ON_ERROR)),
        ];
    }

    private function accountState(User $user): ?JourneyState
    {
        if (! $user->hasVerifiedEmail()) {
            return JourneyState::VERIFICATION;
        }
        if ($user->status === StatusEnum::PENDING) {
            return JourneyState::PENDING;
        }
        if ($user->status === StatusEnum::REJECTED) {
            return JourneyState::REJECTED;
        }
        if ($this->access->isStaff($user)) {
            return JourneyState::READY;
        }
        if (! $this->hasVerifiedLegalProfile($user)) {
            return JourneyState::PENDING;
        }

        return null;
    }

    private function hasVerifiedLegalProfile(User $user): bool
    {
        return $user->legal_verification_status === VerificationStatusEnum::VERIFIED
            && $user->legal_verified_at !== null
            && $user->legal_profile_version !== null;
    }

    /** @return Collection<int, Tenant> */
    private function loadTenants(User $user): Collection
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = $user->tenants()->orderBy('id')
            ->get(['id', 'uuid', 'user_id', 'shop_name', 'status', 'schema_version', 'provisioned_at']);

        return $tenants;
    }

    /** @param Collection<int, Tenant> $tenants */
    private function stateFromTenants(Collection $tenants): JourneyState
    {
        if ($tenants->isEmpty()) {
            return JourneyState::SETUP;
        }

        return $tenants->contains(fn (Tenant $tenant): bool => $this->isReady($tenant))
            ? JourneyState::READY
            : JourneyState::PREPARING;
    }

    private function isPrepared(Tenant $tenant): bool
    {
        return $tenant->provisioned_at !== null && filled($tenant->schema_version);
    }

    private function isReady(Tenant $tenant): bool
    {
        return $this->isPrepared($tenant) && $tenant->status === TenantStatus::ACTIVE;
    }

    /** @return ShopProgress */
    private function shopData(Tenant $tenant, User $user): array
    {
        $tenant->setRelation('owner', $user);
        $failed = $tenant->status === TenantStatus::PROVISIONING_FAILED;

        return [
            'name' => $tenant->shop_name,
            'status' => $tenant->status->label(),
            'prepared' => $this->isPrepared($tenant),
            'failed' => $failed,
            'ready' => $this->isReady($tenant),
            'retry_url' => $failed && Gate::forUser($user)->allows('retryProvisioning', $tenant)
                ? route('owner.shops.retry', $tenant)
                : null,
        ];
    }

    private function refusalReason(User $user): ?string
    {
        $decision = Activity::forSubject($user)
            ->whereIn('event', [self::EVENT_APPROVED, self::EVENT_REJECTED])
            ->orderByDesc('id')->first(['event', 'properties']);

        $reason = $decision?->event === self::EVENT_REJECTED ? $decision->getProperty('reason') : null;

        return is_string($reason) && trim($reason) !== '' ? $reason : null;
    }
}
