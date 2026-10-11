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
        'saas.staff.invite' => 'Inviter et modifier le personnel SaaS',
        'saas.users.roles.assign' => 'Attribuer les rôles du personnel SaaS',
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
