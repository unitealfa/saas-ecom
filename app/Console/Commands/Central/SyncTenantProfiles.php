<?php

namespace App\Console\Commands\Central;

use App\Jobs\Central\SyncTenantProfile;
use App\Models\Central\Tenant;
use Illuminate\Console\Command;

class SyncTenantProfiles extends Command
{
    protected $signature = 'aydra:sync-tenant-profiles';

    protected $description = 'Remettre en file la projection des noms des boutiques déjà préparées.';

    public function handle(): int
    {
        if (tenancy()->initialized) {
            $this->error('Cette commande exige le contexte central.');

            return self::FAILURE;
        }
        $count = 0;
        Tenant::whereNotNull('schema_version')->whereNotNull('provisioned_at')
            ->chunkById(100, function ($tenants) use (&$count): void {
                foreach ($tenants as $tenant) {
                    SyncTenantProfile::dispatch($tenant->id, $tenant->uuid);
                    $count++;
                }
            });
        $this->info($count.' projection(s) remise(s) en file.');

        return self::SUCCESS;
    }
}
