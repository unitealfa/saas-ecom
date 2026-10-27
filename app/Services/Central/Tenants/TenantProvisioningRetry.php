<?php

namespace App\Services\Central\Tenants;

use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;

class TenantProvisioningRetry
{
    public function __construct(private TenantDeploymentService $deployments) {}

    public function retry(User $actor, Tenant $tenant): void
    {
        $this->deployments->retryProvisioning($actor, $tenant);
    }
}
