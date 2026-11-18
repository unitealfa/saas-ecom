<?php

namespace App\Console\Commands;

use App\Models\Central\Tenants\Tenant;
use App\Models\Shared\Authorization\PermissionAssignment;
use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;
use App\Services\Tenant\Authorization\TenantAuthorizationMode;
use App\Services\Tenant\Authorization\TenantPermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Réparation manuelle, jamais au login : une permission explicitement retirée
 * ne doit pas réapparaître sans décision consciente de l'opérateur.
 */
final class TenantOwnerTeamAccessCommand extends Command
{
    protected $signature = 'tenant:owner-team-access {tenant_uuid : UUID public de la boutique} {--apply : Attribuer explicitement les droits absents au propriétaire}';

    protected $description = 'Diagnostiquer les droits de gestion équipe du propriétaire local, puis réparer sur décision explicite';

    public function handle(): int
    {
        if (tenancy()->initialized) {
            $this->error('Commencer cette commande hors du contexte Tenant.');
            return self::FAILURE;
        }

        $tenant = Tenant::where('uuid', $this->argument('tenant_uuid'))->first();
        if ($tenant === null) {
            $this->error('UUID de boutique inconnu.');
            return self::FAILURE;
        }

        try {
            tenancy()->initialize($tenant);
            $shop = Shop::where('singleton', 1)->firstOrFail();
            if ($shop->tenant_uuid !== $tenant->uuid) {
                throw new LogicException('Le profil boutique ne correspond pas au Tenant demandé.');
            }
            if (! TenantAuthorizationMode::individualOnly()) {
                throw new LogicException(
                    'La boutique n’est pas convertie. Utiliser d’abord tenant:authorization-upgrade en diagnostic.',
                );
            }

            $owner = $tenant->owner ?? throw new LogicException('Propriétaire central introuvable.');
            $localOwner = User::where('central_user_uuid', $owner->uuid)->sole();
            if (! TenantAccess::active($localOwner) || $localOwner->email !== $owner->email) {
                throw new LogicException('Le compte du propriétaire local est inactif ou désynchronisé.');
            }

            [$missing, $expired] = $this->snapshot($localOwner);
            $this->line('Boutique : '.$tenant->uuid);
            $this->line('Permissions du module absentes : '.count($missing));
            foreach ($missing as $code) {
                $this->line(' - '.$code);
            }
            if ($expired !== []) {
                $this->warn('Permissions historiques expirées : '.implode(', ', $expired));
                $this->warn('Elles ne seront ni modifiées ni prolongées automatiquement.');
            }

            if (! $this->option('apply')) {
                $this->info('Diagnostic lecture seule terminé ; aucun droit modifié.');
                return self::SUCCESS;
            }

            if ($missing === []) {
                $this->info('Aucune attribution absente. Aucune écriture nécessaire.');
                return self::SUCCESS;
            }

            $this->warn('Une révocation volontaire peut faire partie des droits manquants.');
            if (! $this->confirm('Confirmer explicitement leur réattribution au propriétaire ?', false)) {
                $this->warn('Opération annulée.');
                return self::FAILURE;
            }

            $granted = DB::connection('tenant')->transaction(function () use ($tenant, $owner): array {
                $shop = Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
                if ($shop->tenant_uuid !== $tenant->uuid || ! TenantAuthorizationMode::individualOnly()) {
                    throw new LogicException('Le contexte ou le mode de permissions a changé.');
                }
                $localOwner = User::where('central_user_uuid', $owner->uuid)->lockForUpdate()->sole();
                if (! TenantAccess::active($localOwner) || $localOwner->email !== $owner->email) {
                    throw new LogicException('Le propriétaire a changé de statut.');
                }

                // Ajouter les définitions absentes sans toucher aux droits existants.
                TenantPermissionCatalog::seed();
                [$missing] = $this->snapshot($localOwner);
                $byName = Permission::where('guard_name', Permission::GUARD)
                    ->whereIn('name', $missing)->lockForUpdate()->get()->keyBy('name');
                $now = now();
                foreach ($missing as $code) {
                    $permission = $byName->get($code)
                        ?? throw new LogicException('Permission du catalogue manquante : '.$code);
                    DB::connection('tenant')->table('model_has_permissions')->insert([
                        'permission_id' => $permission->id,
                        'model_type' => $localOwner->getMorphClass(),
                        'model_id' => $localOwner->id,
                        'assigned_at' => $now,
                        'expires_at' => $now->copy()->addDays(PermissionAssignment::NEVER_EXPIRES_DAYS),
                    ]);
                }
                if ($missing !== []) {
                    activity()->performedOn($localOwner)
                        ->event('tenant_owner_team_access_explicitly_restored')
                        ->withProperties(['source' => 'console', 'permissions' => $missing])
                        ->log('Permissions équipe du propriétaire réattribuées sur demande explicite.');
                    DB::connection('tenant')->afterCommit(
                        fn () => app(PermissionRegistrar::class)->forgetCachedPermissions(),
                    );
                }
                return $missing;
            }, 3);

            $this->info(count($granted).' permission(s) ajoutée(s). Les droits préexistants sont conservés.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Contrôle ou réparation impossible : '.$exception->getMessage());
            return self::FAILURE;
        } finally {
            tenancy()->end();
        }
    }

    /** @return array{0:list<string>,1:list<string>} */
    private function snapshot(User $owner): array
    {
        $catalog = array_keys(TenantPermissionCatalog::ALL);
        $definitions = Permission::where('guard_name', Permission::GUARD)
            ->whereIn('name', $catalog)->get()->keyBy('name');
        $grants = DB::connection('tenant')->table('model_has_permissions')
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->id)->get()->keyBy('permission_id');
        $missing = [];
        $expired = [];
        foreach ($catalog as $code) {
            $definition = $definitions->get($code);
            $grant = $definition === null ? null : $grants->get($definition->id);
            if ($grant === null) {
                $missing[] = $code;
            } elseif ($grant->expires_at !== null && $grant->expires_at <= now()) {
                $expired[] = $code;
            }
        }
        return [$missing, $expired];
    }
}
