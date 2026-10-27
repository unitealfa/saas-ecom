<?php

namespace App\Jobs\Central;

use App\Enums\Central\Deployments\OperationEnum;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Tenants\Tenant;
use App\Services\Central\Tenants\TenantProvisioner;
use App\Services\Central\Tenants\TenantStatusService;

class MigrateTenantJob extends ProvisionTenantDatabase
{
    protected function operation(): OperationEnum
    {
        return OperationEnum::MIGRATION;
    }

    protected function execute(TenantProvisioner $provisioner, TenantStatusService $statuses, TenantSchemaDeployment $deployment, Tenant $tenant): void
    {
        $provisioner->migrateExisting($tenant, $deployment->target_version);
        $statuses->completeProvisioning($tenant->refresh(), $deployment->id);
    }
}
