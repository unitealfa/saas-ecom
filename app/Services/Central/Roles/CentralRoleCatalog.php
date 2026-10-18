<?php

namespace App\Services\Central\Roles;

use App\Models\Central\Permission;
use App\Models\Central\Role;
use LogicException;

class CentralRoleCatalog
{
    public const PERMISSIONS = [
        'saas.users.view' => 'Consulter les utilisateurs',
        'saas.users.create' => 'Créer des utilisateurs',
        'saas.users.update' => 'Modifier les utilisateurs',
        'saas.users.approve' => 'Approuver les comptes en attente',
        'saas.users.reject' => 'Refuser les demandes d’accès',
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

    public const GROUPS = [
        'Utilisateurs centraux' => ['saas.users.view', 'saas.users.create', 'saas.users.update', 'saas.users.approve', 'saas.users.reject', 'saas.users.suspend', 'saas.users.activate', 'saas.users.archive'],
        'Dossiers professionnels' => ['saas.users.legal.view', 'saas.users.legal.validate', 'saas.users.legal.reject'],
        'Historique des utilisateurs' => ['saas.users.history.view'],
        'Personnel SaaS' => ['saas.staff.view', 'saas.staff.invite', 'saas.staff.invite.resend', 'saas.staff.update', 'saas.staff.permissions.view', 'saas.staff.assignments.renew', 'saas.staff.suspend', 'saas.staff.activate', 'saas.staff.archive', 'saas.users.roles.assign'],
        'Pays' => ['saas.countries.view', 'saas.countries.create', 'saas.countries.update', 'saas.countries.activate', 'saas.countries.delete'],
        'Rôles SaaS' => ['saas.roles.view', 'saas.roles.create', 'saas.roles.update', 'saas.roles.delete', 'saas.roles.permissions.view', 'saas.roles.permissions.update', 'saas.roles.durations.update', 'saas.roles.assignments.view'],
        'Catalogue des permissions' => ['saas.permissions.view'],
        'Boutiques' => ['saas.tenants.view', 'saas.tenants.create', 'saas.tenants.update', 'saas.tenants.suspend', 'saas.tenants.activate', 'saas.tenants.archive', 'saas.tenants.history.view', 'saas.tenants.priority.update'],
    ];

    public const VIEW_ACTIONS = [
        'saas.users.view' => ['saas.users.create', 'saas.users.update', 'saas.users.approve', 'saas.users.reject', 'saas.users.suspend', 'saas.users.activate', 'saas.users.archive', 'saas.users.legal.view', 'saas.users.legal.validate', 'saas.users.legal.reject', 'saas.users.history.view', 'saas.staff.invite', 'saas.staff.update', 'saas.staff.suspend', 'saas.staff.activate', 'saas.staff.archive', 'saas.users.roles.assign'],
        'saas.staff.view' => ['saas.staff.invite', 'saas.staff.invite.resend', 'saas.staff.update', 'saas.users.roles.assign', 'saas.staff.permissions.view', 'saas.staff.assignments.renew', 'saas.staff.suspend', 'saas.staff.activate', 'saas.staff.archive'],
        'saas.users.legal.view' => ['saas.users.legal.validate', 'saas.users.legal.reject'],
        'saas.countries.view' => ['saas.countries.create', 'saas.countries.update', 'saas.countries.activate', 'saas.countries.delete'],
        'saas.roles.view' => ['saas.roles.create', 'saas.roles.update', 'saas.roles.delete', 'saas.roles.permissions.view', 'saas.roles.permissions.update', 'saas.roles.durations.update', 'saas.roles.assignments.view'],
        'saas.roles.permissions.view' => ['saas.roles.permissions.update', 'saas.roles.durations.update'],
        'saas.tenants.view' => ['saas.tenants.create', 'saas.tenants.update', 'saas.tenants.suspend', 'saas.tenants.activate', 'saas.tenants.archive', 'saas.tenants.history.view', 'saas.tenants.priority.update'],
    ];

    public const ROLES = [
        'user-manager' => [
            'label' => 'Gestionnaire des utilisateurs',
            'permissions' => ['saas.users.view', 'saas.users.create', 'saas.users.update'],
        ],
        'user-reader' => [
            'label' => 'Lecteur',
            'permissions' => ['saas.users.view'],
        ],
    ];

    public function seed(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('The central role catalogue requires the central context.');
        }

        $connection = (new Role)->getConnection();
        $connection->transaction(function (): void {
            $root = Role::where('guard_name', 'central')->where('is_super_admin', true)->first();
            if ($root === null) {
                $root = new Role(['name' => 'root', 'label' => 'Super Admin']);
                $root->forceFill(['is_system' => true, 'is_protected' => true, 'is_super_admin' => true])->save();
            }
            $root = Role::whereKey($root->id)->lockForUpdate()->firstOrFail();
            if (! $root->getAttribute('is_system') || ! $root->getAttribute('is_protected') || $root->guard_name !== 'central') {
                throw new LogicException('The existing root role must remain protected.');
            }

            foreach (['saas.users.restore' => 'saas.users.activate', 'saas.users.delete' => 'saas.users.archive', 'saas.staff.restore' => 'saas.staff.activate', 'saas.staff.delete' => 'saas.staff.archive'] as $old => $new) {
                $legacy = Permission::where('guard_name', 'central')->where('name', $old)->first();
                if ($legacy !== null) {
                    if (Permission::where('guard_name', 'central')->where('name', $new)->exists()) {
                        throw new LogicException('Conflicting permission names require an explicit migration: '.$old.' / '.$new);
                    }
                    $legacy->forceFill(['name' => $new])->save();
                }
            }

            foreach (self::PERMISSIONS as $name => $label) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'central'], ['label' => $label]);
            }

            foreach (self::ROLES as $name => $definition) {
                $expected = array_fill_keys($definition['permissions'], 9999);
                $role = Role::where('name', $name)->where('guard_name', 'central')->lockForUpdate()->first();
                if ($role === null) {
                    $role = Role::createWithPermissions($name, $expected);
                    $role->forceFill(['label' => $definition['label'], 'is_system' => true, 'is_protected' => true])->save();
                } else {
                    $actual = $role->permissions()->get()->mapWithKeys(fn (\Spatie\Permission\Models\Permission $permission): array => [
                        $permission->name => (int) $permission->getRelation('pivot')->getAttribute('duration_days'),
                    ])->all();
                    ksort($expected);
                    ksort($actual);
                    if ($actual !== $expected || ! $role->getAttribute('is_system') || ! $role->getAttribute('is_protected') || $role->getAttribute('is_super_admin')) {
                        throw new LogicException('Refusing to overwrite an existing role with a different definition: '.$name);
                    }
                }
            }
        });
    }
}
