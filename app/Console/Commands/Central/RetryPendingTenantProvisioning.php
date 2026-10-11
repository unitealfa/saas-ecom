<?php

namespace App\Console\Commands\Central;

use App\Enums\Central\Deployments\StatusEnum;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\TenantSchemaDeployment;
use Illuminate\Console\Command;

class RetryPendingTenantProvisioning extends Command
{
    protected $signature = 'aydra:retry-tenant-provisioning';

    protected $description = 'Remettre en file les préparations de boutiques en attente ou en échec.';

    public function handle(): int
    {
        if (tenancy()->initialized) {
            $this->error('Cette commande doit être exécutée dans le contexte central.');

            return self::FAILURE;
        }
        $count = 0;
        TenantSchemaDeployment::where(function ($query): void {
            $query->whereIn('status', [StatusEnum::PENDING, StatusEnum::FAILED])
                ->orWhere(fn ($query) => $query->where('status', StatusEnum::RUNNING)->where('started_at', '<=', now()->subSeconds(360)));
        })->where('operation', 1)->chunkById(100, function ($deployments) use (&$count): void {
            foreach ($deployments as $deployment) {
                ProvisionTenantDatabase::dispatch($deployment->id);
                $count++;
            }
        });
        $this->info($count.' préparation(s) remise(s) en file.');

        return self::SUCCESS;
    }
}
