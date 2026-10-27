<?php

namespace App\Console\Commands\Central;

use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\TenantSchemaDeployment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class RetryPendingTenantProvisioning extends Command
{
    /** Un déploiement RUNNING plus ancien est considéré perdu (timeout du job : 300 s). */
    private const int STALE_RUNNING_SECONDS = 360;

    /** Âge minimal avant de reprendre un déploiement en attente ou en échec : évite le doublon avec le dispatch d'origine. */
    private const int MIN_AGE_SECONDS = 120;

    /** Nombre maximal de reprises automatiques ; au-delà, la reprise est manuelle depuis l'administration. */
    private const int MAX_ATTEMPTS = 5;

    private const int CHUNK_SIZE = 100;

    protected $signature = 'aydra:retry-tenant-provisioning';

    protected $description = 'Remettre en file les préparations de boutiques en attente ou en échec.';

    public function handle(): int
    {
        if (tenancy()->initialized) {
            $this->error('Cette commande doit être exécutée dans le contexte central.');

            return self::FAILURE;
        }

        $count = 0;

        $this->candidates()->chunkById(self::CHUNK_SIZE, function (Collection $deployments) use (&$count): void {
            foreach ($deployments as $deployment) {
                ProvisionTenantDatabase::dispatch($deployment->id);
                $count++;
            }
        });

        $this->info($count.' préparation(s) remise(s) en file.');

        return self::SUCCESS;
    }

    /** Dernier déploiement de préparation de chaque boutique, s'il est en attente, en échec récent ou perdu. */
    private function candidates(): Builder
    {
        $minAge = now()->subSeconds(self::MIN_AGE_SECONDS);

        return TenantSchemaDeployment::query()
            ->where('operation', OperationEnum::PROVISIONING)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $pending) => $pending
                    ->where('status', StatusEnum::PENDING)
                    ->where('created_at', '<=', $minAge))
                ->orWhere(fn (Builder $failed) => $failed
                    ->where('status', StatusEnum::FAILED)
                    ->where('attempt_number', '<', self::MAX_ATTEMPTS)
                    ->where('ended_at', '<=', $minAge))
                ->orWhere(fn (Builder $running) => $running
                    ->where('status', StatusEnum::RUNNING)
                    ->where('started_at', '<=', now()->subSeconds(self::STALE_RUNNING_SECONDS))))
            ->whereNotExists(fn (QueryBuilder $newer) => $newer->selectRaw('1')
                ->from('tenant_schema_deployments as newer')
                ->whereColumn('newer.tenant_id', 'tenant_schema_deployments.tenant_id')
                ->whereColumn('newer.id', '>', 'tenant_schema_deployments.id'));
    }
}