<?php

namespace App\Policies\Central;

use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class TenantSchemaDeploymentPolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.deployments.view');
    }

    public function view(User $actor, TenantSchemaDeployment $deployment): bool
    {
        return $this->viewAny($actor);
    }

    public function provision(User $actor, TenantSchemaDeployment $deployment): bool
    {
        return $this->available($deployment->tenant)
            && $deployment->operation === OperationEnum::PROVISIONING
            && $deployment->status === StatusEnum::PENDING
            && $this->access->allows($actor, 'saas.deployments.provision');
    }

    public function migrate(User $actor, Tenant $tenant): bool
    {
        return $this->available($tenant) && $tenant->provisioned_at !== null && filled($tenant->schema_version)
            && in_array($tenant->status, [TenantStatus::ACTIVE, TenantStatus::INACTIVE, TenantStatus::SUSPENDED, TenantStatus::OVER_QUOTA], true)
            && $this->access->allows($actor, 'saas.deployments.migrate');
    }

    public function retry(User $actor, TenantSchemaDeployment $deployment): bool
    {
        return $this->available($deployment->tenant) && $deployment->status === StatusEnum::FAILED
            && $this->access->allows($actor, 'saas.deployments.retry');
    }

    public function viewErrors(User $actor, TenantSchemaDeployment $deployment): bool
    {
        return $this->access->allows($actor, 'saas.deployments.errors.view');
    }

    private function available(?Tenant $tenant): bool
    {
        return $tenant !== null && ! $tenant->trashed();
    }
}
