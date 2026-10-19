<?php

namespace App\Policies\Central;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Enums\VerificationStatusEnum;

class TenantPolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'saas.tenants.view');
    }

    public function view(User $actor, Tenant $tenant): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor, ?User $owner = null): bool
    {
        return $this->allows($actor, 'saas.tenants.create') && ($owner === null
            || (! $owner->trashed() && $owner->status === StatusEnum::ACTIVE && ! $this->access->isStaff($owner)));
    }

    public function update(User $actor, Tenant $tenant): bool
    {
        return ! $tenant->trashed() && $this->allows($actor, 'saas.tenants.update');
    }

    public function createFirst(User $actor, User $owner): bool
    {
        return $this->createOwned($actor, $owner);
    }

    public function createOwned(User $actor, User $owner): bool
    {
        return $actor->id === $owner->id && ! $owner->trashed()
            && $owner->status === StatusEnum::ACTIVE && $owner->hasVerifiedEmail()
            && $owner->legal_verification_status === VerificationStatusEnum::VERIFIED
            && $owner->legal_verified_at !== null && $owner->legal_profile_version !== null
            && ! $this->access->isStaff($owner);
    }

    public function retryProvisioning(User $actor, Tenant $tenant): bool
    {
        return ! $tenant->trashed() && ($this->createOwned($actor, $tenant->owner)
            || $this->allows($actor, 'saas.tenants.create'));
    }

    public function suspend(User $actor, Tenant $tenant): bool
    {
        return ! $tenant->trashed() && $this->allows($actor, 'saas.tenants.suspend');
    }

    public function activate(User $actor, Tenant $tenant): bool
    {
        return ! $tenant->trashed() && in_array($tenant->status, [\App\Enums\Central\Tenants\StatusEnum::INACTIVE,
            \App\Enums\Central\Tenants\StatusEnum::SUSPENDED, \App\Enums\Central\Tenants\StatusEnum::OVER_QUOTA], true)
            && $this->allows($actor, 'saas.tenants.activate');
    }

    public function archive(User $actor, Tenant $tenant): bool
    {
        return ! $tenant->trashed() && $this->allows($actor, 'saas.tenants.archive');
    }

    public function viewHistory(User $actor, Tenant $tenant): bool
    {
        return $this->allows($actor, 'saas.tenants.history.view');
    }

    public function updatePriorities(User $actor, Tenant $tenant): bool
    {
        return ! $tenant->trashed() && $this->allows($actor, 'saas.tenants.priority.update');
    }

    private function allows(User $actor, string $permission): bool
    {
        return $this->access->isSuperAdmin($actor) || $this->access->allows($actor, $permission);
    }
}
