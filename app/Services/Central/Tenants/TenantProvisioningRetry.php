<?php

namespace App\Services\Central\Tenants;

use App\Enums\Central\Deployments\StatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TenantProvisioningRetry
{
    public function __construct(private AuthorizationMutation $mutations) {}

    public function retry(User $actor, Tenant $tenant): void
    {
        $this->mutations->run(function () use ($actor, $tenant): void {
            User::whereKey($tenant->user_id)->lockForUpdate()->firstOrFail();
            $tenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('retryProvisioning', $tenant);
            $deployment = TenantSchemaDeployment::where('tenant_id', $tenant->id)->where('operation', 1)->lockForUpdate()->firstOrFail();
            if (in_array($deployment->status, [StatusEnum::PENDING, StatusEnum::RUNNING], true)) {
                return;
            }
            if ($deployment->status !== StatusEnum::FAILED || $tenant->status !== TenantStatus::PROVISIONING_FAILED) {
                throw ValidationException::withMessages(['tenant' => 'Cette boutique ne nécessite pas de reprise.']);
            }
            $deployment->update(['status' => StatusEnum::PENDING, 'ended_at' => null, 'error_code' => null, 'sanitized_error' => null]);
            $tenant->status = TenantStatus::PROVISIONING;
            $tenant->save();
            ProvisionTenantDatabase::dispatch($deployment->id)->beforeCommit();
            activity()->causedBy($actor)->performedOn($tenant)->event('tenant_provisioning_retried')
                ->withProperties(['deployment_uuid' => $deployment->uuid])->log('Reprise de la préparation sans nouvelle boutique.');
        });
    }
}
