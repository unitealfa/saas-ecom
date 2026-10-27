<?php

namespace App\Console\Commands\Central;

use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class RetryPendingTenantProvisioning extends Command
{
    /** Un déploiement RUNNING plus ancien est considéré perdu (timeout du job : 300 s). */
    private const int STALE_RUNNING_SECONDS = 360;

    /** Âge minimal du rattrapage planifié ; une reprise manuelle reste immédiate. */
    private const int MIN_AGE_SECONDS = 120;

    /** Le rattrapage planifié ne sélectionne plus les échecs à partir de ce numéro ; le worker conserve ses propres essais. */
    private const int MAX_ATTEMPTS = 5;

    private const int CHUNK_SIZE = 100;

    protected $signature = 'aydra:retry-tenant-provisioning {--scheduled : Appliquer le délai et la limite du rattrapage planifié}';

    protected $description = 'Remettre en file les préparations de boutiques en attente ou en échec.';

    public function handle(): int
    {
        if (tenancy()->initialized) {
            $this->error('Cette commande doit être exécutée dans le contexte central.');

            return self::FAILURE;
        }

        $count = 0;

        foreach ($this->candidates()->lazyById(self::CHUNK_SIZE) as $deployment) {
            ProvisionTenantDatabase::dispatch($deployment->id);
            $count++;
        }

        $this->info($count.' préparation(s) remise(s) en file.');

        return self::SUCCESS;
    }

    /**
     * Dernière préparation en attente, en échec ou perdue ; délais et limite supplémentaires en mode planifié.
     *
     * @return Builder<TenantSchemaDeployment>
     */
    private function candidates(): Builder
    {
        $minAge = now()->subSeconds(self::MIN_AGE_SECONDS);
        $scheduled = (bool) $this->option('scheduled');

        return TenantSchemaDeployment::query()
            ->where('operation', OperationEnum::PROVISIONING)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $pending) => $pending
                    ->where('status', StatusEnum::PENDING)
                    ->when($scheduled, fn (Builder $pending) => $pending->where('created_at', '<=', $minAge)))
                ->orWhere(fn (Builder $failed) => $failed
                    ->where('status', StatusEnum::FAILED)
                    ->when($scheduled, fn (Builder $failed) => $failed
                        ->where('attempt_number', '<', self::MAX_ATTEMPTS)
                        ->where('ended_at', '<=', $minAge)))
                ->orWhere(fn (Builder $running) => $running
                    ->where('status', StatusEnum::RUNNING)
                    ->where('started_at', '<=', now()->subSeconds(self::STALE_RUNNING_SECONDS))))
            ->whereNotExists(fn (QueryBuilder $newer) => $newer->selectRaw('1')
                ->from('tenant_schema_deployments as newer')
                ->whereColumn('newer.tenant_id', 'tenant_schema_deployments.tenant_id')
                ->whereColumn('newer.id', '>', 'tenant_schema_deployments.id'));
    }
}
