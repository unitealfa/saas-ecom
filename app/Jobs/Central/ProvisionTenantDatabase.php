<?php

namespace App\Jobs\Central;

use App\Enums\Central\Deployments\StatusEnum;
use App\Models\Central\TenantSchemaDeployment;
use App\Services\Central\Tenants\TenantProvisioner;
use App\Services\Central\Tenants\TenantStatusService;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProvisionTenantDatabase implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $deploymentId)
    {
        $this->onConnection('central-provisioning');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(TenantProvisioner $provisioner, TenantStatusService $statuses): void
    {
        $connection = DB::connection(config('tenancy.database.central_connection'));
        if (tenancy()->initialized || $connection->transactionLevel() > 0) {
            throw new \LogicException('Provisioning requires the committed central context.');
        }
        $tenantId = TenantSchemaDeployment::whereKey($this->deploymentId)->value('tenant_id');
        if ($tenantId === null) {
            throw new \LogicException('The provisioning operation no longer exists.');
        }
        $lock = 'aydra:provision:'.hash('sha1', $connection->getDatabaseName().':'.$tenantId);
        if ((int) $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired !== 1) {
            return;
        }
        try {
            $this->runProvisioning($provisioner, $statuses);
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    }

    private function runProvisioning(TenantProvisioner $provisioner, TenantStatusService $statuses): void
    {
        $claimed = (new TenantSchemaDeployment)->getConnection()->transaction(function (): bool {
            $deployment = TenantSchemaDeployment::whereKey($this->deploymentId)->lockForUpdate()->firstOrFail();
            if (in_array($deployment->status, [StatusEnum::SUCCEEDED, StatusEnum::CANCELLED], true)) {
                return false;
            }
            $deployment->update([
                'status' => StatusEnum::RUNNING, 'started_at' => now(), 'ended_at' => null,
                'error_code' => null, 'sanitized_error' => null,
                'attempt_number' => $deployment->started_at === null ? 1 : $deployment->attempt_number + 1,
            ]);

            return true;
        });

        if (! $claimed) {
            return;
        }
        $deployment = null;
        try {
            $deployment = TenantSchemaDeployment::findOrFail($this->deploymentId);
            $tenant = $deployment->tenant()->firstOrFail();
            $tenant->refresh();
            if (in_array($tenant->status, [TenantStatus::ACTIVE, TenantStatus::INACTIVE, TenantStatus::OVER_QUOTA, TenantStatus::SUSPENDED], true)
                && $tenant->provisioned_at !== null && filled($tenant->schema_version)) {
                $deployment->update(['status' => StatusEnum::SUCCEEDED, 'ended_at' => now()]);
                return;
            }
            $provisioner->provision($tenant);
            $statuses->completeProvisioning($tenant->fresh(), $deployment->id);
        } catch (Throwable $exception) {
            TenantSchemaDeployment::whereKey($this->deploymentId)->whereNotIn('status', [StatusEnum::SUCCEEDED, StatusEnum::CANCELLED])->update([
                'status' => StatusEnum::FAILED, 'ended_at' => now(),
                'error_code' => 'PROVISIONING_FAILED',
                'sanitized_error' => 'La préparation de la boutique a échoué. Consulter le diagnostic technique privé.',
            ]);
            Tenant::whereKey($deployment?->tenant_id)->whereIn('status', [TenantStatus::PROVISIONING, TenantStatus::PROVISIONING_FAILED])
                ->update(['status' => TenantStatus::PROVISIONING_FAILED]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $deployment = TenantSchemaDeployment::find($this->deploymentId);
        if ($deployment === null || in_array($deployment->status, [StatusEnum::SUCCEEDED, StatusEnum::CANCELLED], true)) {
            return;
        }
        $deployment->getConnection()->transaction(function () use ($deployment): void {
            $deployment = TenantSchemaDeployment::whereKey($deployment->id)->lockForUpdate()->firstOrFail();
            if (in_array($deployment->status, [StatusEnum::SUCCEEDED, StatusEnum::CANCELLED], true)) {
                return;
            }
            $deployment->update(['status' => StatusEnum::FAILED, 'ended_at' => now(),
                'error_code' => 'PROVISIONING_EXHAUSTED', 'sanitized_error' => 'La préparation a échoué après plusieurs tentatives. Une reprise est possible.']);
            Tenant::whereKey($deployment->tenant_id)->whereIn('status', [TenantStatus::PROVISIONING, TenantStatus::PROVISIONING_FAILED])
                ->update(['status' => TenantStatus::PROVISIONING_FAILED]);
        }, 3);
        Log::error('Tenant provisioning attempts exhausted.', ['deployment_uuid' => $deployment->uuid, 'exception_class' => $exception ? $exception::class : null]);
    }
}
