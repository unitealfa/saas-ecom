<?php

namespace App\Services\Central\Tenants;

use App\Models\Central\Tenant;
use App\Models\Central\User;

class TenantProvisioningRetry
{
    public function __construct(private TenantDeploymentService $deployments) {}

    public function retry(User $actor, Tenant $tenant): void
    {
        $this->deployments->retryProvisioning($actor, $tenant);
    }
}
