<?php

namespace App\Services\Central;

use App\Models\Central\Permission;
use App\Models\Central\Role;
use LogicException;

class CentralRoleCatalog
{
    public const PERMISSIONS = [
        'saas.users.view' => 'Consulter les utilisateurs',
        'saas.users.create' => 'Créer des comptes commerçants',
        'saas.users.update' => 'Modifier les comptes commerçants',
        'saas.users.suspend' => 'Suspendre les comptes commerçants',
        'saas.users.restore' => 'Réactiver les comptes commerçants',
        'saas.users.delete' => 'Supprimer les comptes commerçants',
        'saas.staff.invite' => 'Inviter le personnel SaaS',
        'saas.staff.update' => 'Modifier le personnel SaaS',
        'saas.staff.suspend' => 'Suspendre le personnel SaaS',
        'saas.staff.restore' => 'Réactiver le personnel SaaS',
        'saas.staff.delete' => 'Supprimer le personnel SaaS',
        'saas.users.roles.assign' => 'Attribuer les rôles du personnel SaaS',
        'saas.countries.view' => 'Consulter les pays',
        'saas.countries.create' => 'Créer des pays',
        'saas.countries.update' => 'Modifier les pays',
        'saas.countries.activate' => 'Activer ou désactiver les pays',
        'saas.countries.delete' => 'Retirer des pays',
    ];

    public const GROUPS = [
        'Comptes commerçants' => ['saas.users.view', 'saas.users.create', 'saas.users.update', 'saas.users.suspend', 'saas.users.restore', 'saas.users.delete'],
        'Personnel SaaS' => ['saas.staff.invite', 'saas.staff.update', 'saas.staff.suspend', 'saas.staff.restore', 'saas.staff.delete', 'saas.users.roles.assign'],
        'Pays' => ['saas.countries.view', 'saas.countries.create', 'saas.countries.update', 'saas.countries.activate', 'saas.countries.delete'],
    ];

    public const VIEW_ACTIONS = [
        'saas.users.view' => ['saas.users.create', 'saas.users.update', 'saas.users.suspend', 'saas.users.restore', 'saas.users.delete', 'saas.staff.invite', 'saas.staff.update', 'saas.staff.suspend', 'saas.staff.restore', 'saas.staff.delete', 'saas.users.roles.assign'],
        'saas.countries.view' => ['saas.countries.create', 'saas.countries.update', 'saas.countries.activate', 'saas.countries.delete'],
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

            foreach (self::PERMISSIONS as $name => $label) {
                $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'central'], ['label' => $label]);
                if ($permission->label !== $label) {
                    $permission->forceFill(['label' => $label])->save();
                }
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
