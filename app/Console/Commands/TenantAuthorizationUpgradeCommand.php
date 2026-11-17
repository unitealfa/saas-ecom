<?php

namespace App\Console\Commands;

use App\Models\Central\Tenants\Tenant;
use App\Services\Tenant\Authorization\AuthorizationUpgradeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class TenantAuthorizationUpgradeCommand extends Command
{
    protected $signature = 'tenant:authorization-upgrade {tenant_uuid : UUID public de la boutique} {--apply : Convertir après diagnostic}';
    protected $description = 'Diagnostic sans écriture ou conversion des droits hérités pour une seule boutique';

    public function handle(AuthorizationUpgradeService $upgrade): int
    {
        if (tenancy()->initialized) {
            $this->error('Cette commande doit commencer en contexte central.');
            return self::FAILURE;
        }
        $tenant = Tenant::where('uuid', $this->argument('tenant_uuid'))->first();
        if (! $tenant) {
            $this->error('Boutique inconnue.');
            return self::FAILURE;
        }
        try {
            tenancy()->initialize($tenant);
            $shop = \App\Models\Tenant\Shop\Shop::where('singleton', 1)->first();
            if (! $shop || $shop->tenant_uuid !== $tenant->uuid) {
                throw new \LogicException('Identité du profil boutique non conforme.');
            }
            $result = $this->option('apply') ? $upgrade->apply() : $upgrade->diagnose();
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if (! $result['ready']) {
                $this->warn('Conflits détectés : aucune conversion automatique.');
                return self::FAILURE;
            }
            $this->info($this->option('apply') ? 'Conversion contrôlée terminée.' : 'Diagnostic lecture seule terminé.');
            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('Diagnostic/conversion impossible : '.$error->getMessage());
            return self::FAILURE;
        } finally {
            tenancy()->end();
        }
    }
}
