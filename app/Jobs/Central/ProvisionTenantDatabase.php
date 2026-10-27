<?php

namespace App\Jobs\Central;

use App\Enums\Central\Deployments\ErrorCodeEnum;
use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Tenants\Tenant;
use App\Services\Central\Tenants\TenantDeploymentService;
use App\Services\Central\Tenants\TenantProvisioner;
use App\Services\Central\Tenants\TenantStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

class ProvisionTenantDatabase implements ShouldQueue
{
    use Queueable;

    /** Un seul déploiement à la fois par boutique (verrou MySQL, 64 caractères maximum). */
    private const string LOCK_PREFIX = 'aydra:provision:';

    private const int LOCK_RETRY_SECONDS = 15;

    private const int TRANSACTION_ATTEMPTS = 3;

    private const string MESSAGE_FAILED = 'L’opération technique a échoué. Une reprise est possible.';

    private const string MESSAGE_EXHAUSTED = 'L’opération a été interrompue. Une reprise est possible.';

    /** Un déploiement terminé ne se relance plus. */
    private const array FINISHED = [StatusEnum::SUCCEEDED, StatusEnum::CANCELLED];

    /** Statuts de boutique qui deviennent « échec de préparation » quand un déploiement échoue. */
    private const array PROVISIONING_STATUSES = [TenantStatus::PROVISIONING, TenantStatus::PROVISIONING_FAILED];

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

    public function handle(TenantProvisioner $provisioner, TenantStatusService $statuses, TenantDeploymentService $deployments): void
    {
        $connection = $this->centralConnection();

        if (tenancy()->initialized || $connection->transactionLevel() > 0) {
            throw new LogicException('Deployment requires the committed central context.');
        }

        $original = TenantSchemaDeployment::findOrFail($this->deploymentId);

        if ($original->operation !== $this->operation()) {
            throw new LogicException('The job does not match the deployment operation.');
        }

        $lock = $this->lockName($connection, $original->tenant_id);

        if (! $this->acquireLock($connection, $lock)) {
            $this->release(self::LOCK_RETRY_SECONDS);

            return;
        }

        try {
            $deployment = $this->start($connection, $original, $deployments);

            if ($deployment === null) {
                return;
            }

            try {
                $this->execute($provisioner, $statuses, $deployment, Tenant::findOrFail($deployment->tenant_id));
            } catch (Throwable $exception) {
                $this->recordFailure($deployment);

                throw $exception;
            }
        } finally {
            tenancy()->end();
            $this->releaseLock($connection, $lock);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $original = TenantSchemaDeployment::find($this->deploymentId);

        if ($original === null) {
            return;
        }

        $connection = $this->centralConnection();
        $lock = $this->lockName($connection, $original->tenant_id);

        if (! $this->acquireLock($connection, $lock)) {
            return;
        }

        try {
            $connection->transaction(function () use ($original): void {
                Tenant::whereKey($original->tenant_id)->lockForUpdate()->first();

                $latest = TenantSchemaDeployment::where('tenant_id', $original->tenant_id)
                    ->latest('id')->lockForUpdate()->firstOrFail();

                $stillOpen = $latest->status === StatusEnum::RUNNING
                    || ($latest->id === $original->id && $latest->status === StatusEnum::PENDING);

                if ($latest->correlation_id !== $original->correlation_id || ! $stillOpen) {
                    return;
                }

                $latest->update([
                    'status' => StatusEnum::FAILED,
                    'ended_at' => now(),
                    'error_code' => ErrorCodeEnum::DEPLOYMENT_EXHAUSTED->value,
                    'sanitized_error' => self::MESSAGE_EXHAUSTED,
                ]);

                $this->markTenantProvisioningFailed($latest->tenant_id);
            }, self::TRANSACTION_ATTEMPTS);
        } finally {
            $this->releaseLock($connection, $lock);
        }

        Log::error('Tenant deployment attempts exhausted.', [
            'deployment_uuid' => $original->uuid,
            'exception_class' => $exception ? $exception::class : null,
        ]);
    }

    protected function operation(): OperationEnum
    {
        return OperationEnum::PROVISIONING;
    }

    protected function execute(TenantProvisioner $provisioner, TenantStatusService $statuses, TenantSchemaDeployment $deployment, Tenant $tenant): void
    {
        $alreadyProvisioned = ! in_array($tenant->status, self::PROVISIONING_STATUSES, true)
            && $tenant->provisioned_at !== null
            && filled($tenant->schema_version);

        // Reprise idempotente : une boutique déjà préparée marque simplement le déploiement comme réussi.
        if ($alreadyProvisioned) {
            $deployment->update(['status' => StatusEnum::SUCCEEDED, 'ended_at' => now()]);

            return;
        }

        $provisioner->provision($tenant, $deployment->target_version);
        $statuses->completeProvisioning($tenant->refresh(), $deployment->id);
    }

    /** Passe le déploiement en cours (le dernier de la boutique, s'il est de cette exécution) à RUNNING. */
    private function start(Connection $connection, TenantSchemaDeployment $original, TenantDeploymentService $deployments): ?TenantSchemaDeployment
    {
        return $connection->transaction(function () use ($original, $deployments): ?TenantSchemaDeployment {
            $tenant = Tenant::whereKey($original->tenant_id)->lockForUpdate()->first();

            if ($tenant === null) {
                return null;
            }

            $latest = TenantSchemaDeployment::where('tenant_id', $tenant->id)->latest('id')->lockForUpdate()->firstOrFail();

            if ($latest->correlation_id !== $original->correlation_id || in_array($latest->status, self::FINISHED, true)) {
                return null;
            }

            // Le mutex est libre : une ancienne tentative RUNNING n'a plus de worker propriétaire.
            if ($latest->status === StatusEnum::RUNNING) {
                $latest->update([
                    'status' => StatusEnum::FAILED,
                    'ended_at' => now(),
                    'error_code' => ErrorCodeEnum::DEPLOYMENT_EXHAUSTED->value,
                    'sanitized_error' => self::MESSAGE_EXHAUSTED,
                ]);
            }

            if ($latest->status === StatusEnum::FAILED) {
                $latest = $deployments->reserveRetry($latest);
            }

            $latest->update(['status' => StatusEnum::RUNNING, 'started_at' => now(), 'ended_at' => null]);

            return $latest;
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function recordFailure(TenantSchemaDeployment $deployment): void
    {
        TenantSchemaDeployment::whereKey($deployment->id)->whereNotIn('status', self::FINISHED)->update([
            'status' => StatusEnum::FAILED,
            'ended_at' => now(),
            'error_code' => ($this->operation() === OperationEnum::PROVISIONING
                ? ErrorCodeEnum::PROVISIONING_FAILED
                : ErrorCodeEnum::MIGRATION_FAILED)->value,
            'sanitized_error' => self::MESSAGE_FAILED,
        ]);

        $this->markTenantProvisioningFailed($deployment->tenant_id);
    }

    private function markTenantProvisioningFailed(int $tenantId): void
    {
        Tenant::whereKey($tenantId)->whereIn('status', self::PROVISIONING_STATUSES)
            ->update(['status' => TenantStatus::PROVISIONING_FAILED]);
    }

    private function centralConnection(): Connection
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }

    private function lockName(Connection $connection, int $tenantId): string
    {
        return self::LOCK_PREFIX.hash('sha1', $connection->getDatabaseName().':'.$tenantId);
    }

    private function acquireLock(Connection $connection, string $lock): bool
    {
        return (int) $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired === 1;
    }

    private function releaseLock(Connection $connection, string $lock): void
    {
        $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
    }
}
