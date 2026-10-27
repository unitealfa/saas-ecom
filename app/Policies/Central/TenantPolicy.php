<?php

namespace App\Policies\Central;

use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class TenantPolicy
{
    /** Statuts depuis lesquels une boutique peut être réactivée. */
    private const array ACTIVATABLE = [TenantStatus::INACTIVE, TenantStatus::SUSPENDED, TenantStatus::OVER_QUOTA];

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
        return $this->allows($actor, 'saas.tenants.create')
            && ($owner === null || $this->canOwnShops($owner));
    }

    public function update(User $actor, Tenant $tenant): bool
    {
        return ! $tenant->trashed() && $this->allows($actor, 'saas.tenants.update');
    }

    public function createFirst(User $actor, User $owner): bool
    {
        return $this->createOwned($actor, $owner);
    }

    /** Un propriétaire crée ses propres boutiques, une fois son compte actif et son dossier légal validé. */
    public function createOwned(User $actor, User $owner): bool
    {
        return $actor->id === $owner->id
            && $this->canOwnShops($owner)
            && $owner->hasVerifiedEmail()
            && $owner->legal_verification_status === VerificationStatusEnum::VERIFIED
            && $owner->legal_verified_at !== null
            && $owner->legal_profile_version !== null;
    }

    public function retryProvisioning(User $actor, Tenant $tenant): bool
    {
        if ($tenant->trashed()) {
            return false;
        }

        // Un propriétaire archivé n'a plus de relation : seul le droit d'administration reste possible.
        $ownerMayRetry = $tenant->owner !== null && $this->createOwned($actor, $tenant->owner);

        return ($ownerMayRetry || $this->allows($actor, 'saas.tenants.create'))
            && $this->latestOperation($tenant) === OperationEnum::PROVISIONING;
    }

    public function suspend(User $actor, Tenant $tenant): bool
    {
        return ! $tenant->trashed() && $this->allows($actor, 'saas.tenants.suspend');
    }

    public function activate(User $actor, Tenant $tenant): bool
    {
        return ! $tenant->trashed()
            && in_array($tenant->status, self::ACTIVATABLE, true)
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

    /** Un propriétaire de boutique est un compte actif, non archivé et qui n'est pas du personnel. */
    private function canOwnShops(User $owner): bool
    {
        return ! $owner->trashed()
            && $owner->status === StatusEnum::ACTIVE
            && ! $this->access->isStaff($owner);
    }

    private function latestOperation(Tenant $tenant): ?OperationEnum
    {
        return TenantSchemaDeployment::where('tenant_id', $tenant->id)
            ->latest('id')
            ->first(['operation'])
            ?->operation;
    }

    private function allows(User $actor, string $permission): bool
    {
        return $this->access->isSuperAdmin($actor) || $this->access->allows($actor, $permission);
    }
}
