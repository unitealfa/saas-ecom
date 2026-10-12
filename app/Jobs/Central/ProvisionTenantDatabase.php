<?php

namespace App\Jobs\Central;

use App\Enums\Central\Deployments\StatusEnum;
use App\Models\Central\TenantSchemaDeployment;
use App\Services\Central\Tenants\TenantProvisioner;
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

    public function handle(TenantProvisioner $provisioner): void
    {
        $claimed = (new TenantSchemaDeployment)->getConnection()->transaction(function (): bool {
            $deployment = TenantSchemaDeployment::whereKey($this->deploymentId)->lockForUpdate()->firstOrFail();
            if (in_array($deployment->status, [StatusEnum::SUCCEEDED, StatusEnum::CANCELLED], true)
                || ($deployment->status === StatusEnum::RUNNING && $deployment->started_at?->gt(now()->subSeconds(360)))) {
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
        try {
            $deployment = TenantSchemaDeployment::findOrFail($this->deploymentId);
            $provisioner->provision($deployment->tenant()->firstOrFail());
            $deployment->update(['status' => StatusEnum::SUCCEEDED, 'ended_at' => now()]);
        } catch (Throwable $exception) {
            TenantSchemaDeployment::whereKey($this->deploymentId)->update([
                'status' => StatusEnum::FAILED, 'ended_at' => now(),
                'error_code' => 'PROVISIONING_FAILED',
                'sanitized_error' => 'La préparation de la boutique a échoué. Consulter le diagnostic technique privé.',
            ]);
            throw $exception;
        }
    }
}
