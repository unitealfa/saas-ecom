<?php

namespace App\Services\Central\Roles;

use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Shared\Authorization\PermissionAssignment;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Permission as PackagePermission;

class CentralRoleCatalog
{
    public const array PLAN_PERMISSIONS = [
        'saas.plans.view' => 'Consulter les plans et leurs versions',
        'saas.plans.create' => 'Créer un nouveau plan',
        'saas.plans.features.update' => 'Définir les fonctionnalités et quotas d’un plan',
        'saas.plans.versions.create' => 'Créer une nouvelle version d’un plan',
        'saas.plans.activate' => 'Activer ou désactiver la commercialisation d’un plan',
    ];

    public const array CARRIER_PERMISSIONS = [
        'saas.carriers.view' => 'Consulter les transporteurs',
        'saas.carriers.create' => 'Ajouter un transporteur',
        'saas.carriers.update' => 'Modifier les informations d’un transporteur',
        'saas.carriers.activate' => 'Activer ou désactiver un transporteur',
        'saas.carriers.archive' => 'Archiver un transporteur autorisé',
    ];

    public const array GEOGRAPHY_PERMISSIONS = [
        'saas.geography.view' => 'Consulter les wilayas et communes',
        'saas.geography.create' => 'Ajouter une zone géographique',
        'saas.geography.update' => 'Modifier une zone géographique',
        'saas.geography.activate' => 'Activer ou désactiver une zone',
        'saas.geography.archive' => 'Archiver une zone autorisée',
    ];

    public const array FEATURE_PERMISSIONS = [
        'saas.features.view' => 'Consulter les fonctionnalités commerciales',
        'saas.features.create' => 'Créer une fonctionnalité',
        'saas.features.update' => 'Modifier les paramètres d’une fonctionnalité',
        'saas.features.activate' => 'Activer ou désactiver une fonctionnalité',
        'saas.features.archive' => 'Archiver une fonctionnalité inutilisée',
    ];

    public const array DEPLOYMENT_PERMISSIONS = [
        'saas.deployments.view' => 'Consulter les déploiements des BDD boutiques',
        'saas.deployments.provision' => 'Lancer un provisionnement autorisé',
        'saas.deployments.migrate' => 'Lancer une migration vers une version prévue',
        'saas.deployments.retry' => 'Relancer un déploiement en échec',
        'saas.deployments.errors.view' => 'Consulter les erreurs techniques nettoyées',
    ];

    public const array DOMAIN_PERMISSIONS = [
        'saas.domains.view' => 'Consulter les domaines des boutiques',
        'saas.domains.create' => 'Ajouter un domaine à une boutique',
        'saas.domains.update' => 'Modifier les informations d’un domaine',
        'saas.domains.verify' => 'Lancer la vérification d’un domaine',
        'saas.domains.primary.update' => 'Définir le domaine principal',
        'saas.domains.archive' => 'Archiver un domaine',
    ];

    public const array PERMISSIONS = [
        ...self::PLAN_PERMISSIONS,
        ...self::CARRIER_PERMISSIONS,
        ...self::GEOGRAPHY_PERMISSIONS,
        ...self::FEATURE_PERMISSIONS,
        ...self::DEPLOYMENT_PERMISSIONS,
        ...self::DOMAIN_PERMISSIONS,
        'saas.users.view' => 'Consulter les utilisateurs',
        'saas.users.create' => 'Créer des utilisateurs',
        'saas.users.update' => 'Modifier les utilisateurs',
        'saas.users.approve' => 'Approuver les comptes et leurs dossiers',
        'saas.users.reject' => 'Refuser les comptes et leurs dossiers avec motif',
        'saas.users.suspend' => 'Suspendre les utilisateurs',
        'saas.users.activate' => 'Réactiver les utilisateurs',
        'saas.users.archive' => 'Archiver les utilisateurs',
        'saas.users.legal.view' => 'Consulter les dossiers professionnels',
        'saas.users.legal.validate' => 'Valider les dossiers professionnels',
        'saas.users.legal.reject' => 'Rejeter les vérifications professionnelles',
        'saas.users.history.view' => 'Consulter l’historique administratif des utilisateurs',
        'saas.staff.view' => 'Consulter le personnel SaaS',
        'saas.staff.invite' => 'Inviter du personnel SaaS',
        'saas.staff.invite.resend' => 'Renvoyer une invitation au personnel SaaS',
        'saas.staff.update' => 'Modifier le personnel SaaS',
        'saas.staff.permissions.view' => 'Consulter les permissions effectives du personnel SaaS',
        'saas.staff.permissions.update' => 'Attribuer ou retirer les permissions individuelles du personnel SaaS',
        'saas.staff.assignments.renew' => 'Renouveler une attribution du personnel SaaS',
        'saas.staff.suspend' => 'Suspendre le personnel SaaS',
        'saas.staff.activate' => 'Réactiver le personnel SaaS',
        'saas.staff.archive' => 'Archiver le personnel SaaS',
        'saas.users.roles.assign' => 'Attribuer les rôles du personnel SaaS',
        'saas.roles.view' => 'Consulter les rôles SaaS',
        'saas.roles.create' => 'Créer un rôle SaaS',
        'saas.roles.update' => 'Modifier le nom et les informations d’un rôle',
        'saas.roles.delete' => 'Supprimer un rôle personnalisable inutilisé',
        'saas.roles.permissions.view' => 'Consulter les permissions associées à un rôle',
        'saas.roles.permissions.update' => 'Ajouter ou retirer des permissions d’un rôle',
        'saas.roles.durations.update' => 'Modifier les durées des permissions d’un rôle',
        'saas.roles.assignments.view' => 'Consulter les administrateurs possédant un rôle',
        'saas.permissions.view' => 'Consulter et rechercher le catalogue des permissions',
        'saas.tenants.view' => 'Consulter et rechercher les boutiques',
        'saas.tenants.create' => 'Créer une boutique depuis l’administration',
        'saas.tenants.update' => 'Modifier les informations centrales d’une boutique',
        'saas.tenants.suspend' => 'Suspendre une boutique',
        'saas.tenants.activate' => 'Réactiver une boutique éligible',
        'saas.tenants.archive' => 'Archiver une boutique',
        'saas.tenants.history.view' => 'Consulter l’historique administratif d’une boutique',
        'saas.tenants.priority.update' => 'Modifier les priorités des boutiques d’un propriétaire',
        'saas.countries.view' => 'Consulter les pays',
        'saas.countries.create' => 'Créer des pays',
        'saas.countries.update' => 'Modifier les pays',
        'saas.countries.activate' => 'Activer ou désactiver les pays',
        'saas.countries.delete' => 'Retirer des pays',
    ];

    public const array GROUPS = [
        'Plans et offres' => ['saas.plans.view', 'saas.plans.create', 'saas.plans.features.update', 'saas.plans.versions.create', 'saas.plans.activate'],
        'Transporteurs' => ['saas.carriers.view', 'saas.carriers.create', 'saas.carriers.update', 'saas.carriers.activate', 'saas.carriers.archive'],
        'Wilayas et communes' => ['saas.geography.view', 'saas.geography.create', 'saas.geography.update', 'saas.geography.activate', 'saas.geography.archive'],
        'Fonctionnalités commerciales' => ['saas.features.view', 'saas.features.create', 'saas.features.update', 'saas.features.activate', 'saas.features.archive'],
        'Déploiements' => ['saas.deployments.view', 'saas.deployments.provision', 'saas.deployments.migrate', 'saas.deployments.retry', 'saas.deployments.errors.view'],
        'Domaines' => ['saas.domains.view', 'saas.domains.create', 'saas.domains.update', 'saas.domains.verify', 'saas.domains.primary.update', 'saas.domains.archive'],
        'Utilisateurs centraux' => ['saas.users.view', 'saas.users.create', 'saas.users.update', 'saas.users.approve', 'saas.users.reject', 'saas.users.suspend', 'saas.users.activate', 'saas.users.archive'],
        'Dossiers professionnels' => ['saas.users.legal.view', 'saas.users.legal.validate', 'saas.users.legal.reject'],
        'Historique des utilisateurs' => ['saas.users.history.view'],
        'Personnel SaaS' => ['saas.staff.view', 'saas.staff.invite', 'saas.staff.invite.resend', 'saas.staff.update', 'saas.staff.permissions.view', 'saas.staff.permissions.update', 'saas.staff.assignments.renew', 'saas.staff.suspend', 'saas.staff.activate', 'saas.staff.archive', 'saas.users.roles.assign'],
        'Pays' => ['saas.countries.view', 'saas.countries.create', 'saas.countries.update', 'saas.countries.activate', 'saas.countries.delete'],
        'Rôles SaaS' => ['saas.roles.view', 'saas.roles.create', 'saas.roles.update', 'saas.roles.delete', 'saas.roles.permissions.view', 'saas.roles.permissions.update', 'saas.roles.durations.update', 'saas.roles.assignments.view'],
        'Catalogue des permissions' => ['saas.permissions.view'],
        'Boutiques' => ['saas.tenants.view', 'saas.tenants.create', 'saas.tenants.update', 'saas.tenants.suspend', 'saas.tenants.activate', 'saas.tenants.archive', 'saas.tenants.history.view', 'saas.tenants.priority.update'],
    ];

    public const array VIEW_ACTIONS = [
        'saas.users.view' => ['saas.users.create', 'saas.users.update', 'saas.users.approve', 'saas.users.reject', 'saas.users.suspend', 'saas.users.activate', 'saas.users.archive', 'saas.users.legal.view', 'saas.users.legal.validate', 'saas.users.legal.reject', 'saas.users.history.view', 'saas.staff.invite', 'saas.staff.update', 'saas.staff.suspend', 'saas.staff.activate', 'saas.staff.archive', 'saas.users.roles.assign'],
        'saas.staff.view' => ['saas.staff.invite', 'saas.staff.invite.resend', 'saas.staff.update', 'saas.users.roles.assign', 'saas.staff.permissions.view', 'saas.staff.assignments.renew', 'saas.staff.suspend', 'saas.staff.activate', 'saas.staff.archive'],
        'saas.users.legal.view' => ['saas.users.approve', 'saas.users.reject', 'saas.users.legal.validate', 'saas.users.legal.reject'],
        'saas.countries.view' => ['saas.countries.create', 'saas.countries.update', 'saas.countries.activate', 'saas.countries.delete'],
        'saas.roles.view' => ['saas.roles.create', 'saas.roles.update', 'saas.roles.delete', 'saas.roles.permissions.view', 'saas.roles.permissions.update', 'saas.roles.durations.update', 'saas.roles.assignments.view'],
        'saas.roles.permissions.view' => ['saas.roles.permissions.update', 'saas.roles.durations.update'],
        'saas.tenants.view' => ['saas.tenants.create', 'saas.tenants.update', 'saas.tenants.suspend', 'saas.tenants.activate', 'saas.tenants.archive', 'saas.tenants.history.view', 'saas.tenants.priority.update'],
    ];

    public const array ROLES = [
        'user-manager' => [
            'label' => 'Gestionnaire des utilisateurs',
            'permissions' => ['saas.users.view', 'saas.users.create', 'saas.users.update'],
        ],
        'user-reader' => [
            'label' => 'Lecteur',
            'permissions' => ['saas.users.view'],
        ],
    ];

    /** Anciens noms de permissions, renommés au seed (ancien => nouveau). */
    private const array RENAMED = [
        'saas.users.restore' => 'saas.users.activate',
        'saas.users.delete' => 'saas.users.archive',
        'saas.staff.restore' => 'saas.staff.activate',
        'saas.staff.delete' => 'saas.staff.archive',
    ];

    public function seed(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('The central role catalogue requires the central context.');
        }

        DB::transaction(function (): void {
            $this->ensureRootRole();
            $this->renameLegacyPermissions();
            $this->ensurePermissions();
            $this->ensureRoles();
        });
    }

    private function ensureRootRole(): void
    {
        $root = Role::forCentralGuard()->where('is_super_admin', true)->first();

        if ($root === null) {
            $root = new Role(['name' => 'root', 'label' => 'Super Admin']);
            $root->forceFill(['is_system' => true, 'is_protected' => true, 'is_super_admin' => true])->save();
        }

        $root = Role::whereKey($root->id)->lockForUpdate()->firstOrFail();

        if (! $root->getAttribute('is_system') || ! $root->getAttribute('is_protected') || $root->guard_name !== Role::GUARD) {
            throw new LogicException('The existing root role must remain protected.');
        }
    }

    private function renameLegacyPermissions(): void
    {
        foreach (self::RENAMED as $old => $new) {
            $legacy = Permission::forCentralGuard()->where('name', $old)->first();

            if ($legacy === null) {
                continue;
            }

            if (Permission::forCentralGuard()->where('name', $new)->exists()) {
                throw new LogicException('Conflicting permission names require an explicit migration: '.$old.' / '.$new);
            }

            $legacy->forceFill(['name' => $new])->save();
        }
    }

    private function ensurePermissions(): void
    {
        foreach (self::PERMISSIONS as $name => $label) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => Permission::GUARD], ['label' => $label]);
        }
    }

    private function ensureRoles(): void
    {
        foreach (self::ROLES as $name => $definition) {
            $expected = array_fill_keys($definition['permissions'], PermissionAssignment::NEVER_EXPIRES_DAYS);
            $role = Role::where('name', $name)->forCentralGuard()->lockForUpdate()->first();

            if ($role === null) {
                $role = Role::createWithPermissions($name, $expected);
                $role->forceFill(['label' => $definition['label'], 'is_system' => true, 'is_protected' => true])->save();

                continue;
            }

            if (! $this->matchesDefinition($role, $expected)) {
                throw new LogicException('Refusing to overwrite an existing role with a different definition: '.$name);
            }
        }
    }

    /** @param array<string, int> $expected nom de permission => durée en jours */
    private function matchesDefinition(Role $role, array $expected): bool
    {
        $actual = $role->permissionComposition()->get()
            ->mapWithKeys(fn (PackagePermission $permission): array => [
                $permission->name => (int) $permission->getRelation('pivot')->getAttribute('duration_days'),
            ])->all();

        ksort($expected);
        ksort($actual);

        return $actual === $expected
            && $role->getAttribute('is_system')
            && $role->getAttribute('is_protected')
            && ! $role->getAttribute('is_super_admin');
    }
}
