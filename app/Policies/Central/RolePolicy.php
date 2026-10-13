<?php

namespace App\Policies\Central;

use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;

class RolePolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->allows($actor, 'saas.roles.view');
    }

    public function create(User $actor): bool
    {
        return $this->allows($actor, 'saas.roles.create');
    }

    public function update(User $actor, Role $role): bool
    {
        return $this->mutable($actor, $role) && $this->allows($actor, 'saas.roles.update');
    }

    public function view(User $actor, Role $role): bool
    {
        return $role->guard_name === 'central' && $this->viewAny($actor);
    }

    public function viewPermissions(User $actor, Role $role): bool
    {
        return $role->guard_name === 'central' && $this->allows($actor, 'saas.roles.permissions.view');
    }

    public function viewAssignments(User $actor, Role $role): bool
    {
        return $role->guard_name === 'central' && $this->allows($actor, 'saas.roles.assignments.view');
    }

    public function updatePermissions(User $actor, Role $role): bool
    {
        return $this->mutable($actor, $role) && $this->allows($actor, 'saas.roles.permissions.update');
    }

    public function updateDurations(User $actor, Role $role): bool
    {
        return $this->mutable($actor, $role) && $this->allows($actor, 'saas.roles.durations.update');
    }

    public function edit(User $actor, Role $role): bool
    {
        return $this->update($actor, $role) || $this->updatePermissions($actor, $role) || $this->updateDurations($actor, $role);
    }

    public function delete(User $actor, Role $role): bool
    {
        return $this->mutable($actor, $role) && $this->allows($actor, 'saas.roles.delete');
    }

    private function mutable(User $actor, Role $role): bool
    {
        return $role->guard_name === 'central' && ! $role->is_protected && ! $role->is_system && ! $role->is_super_admin
            && $this->access->ownsRolePermissions($actor, $role);
    }

    private function allows(User $actor, string $permission): bool
    {
        return $this->access->isSuperAdmin($actor) || $this->access->allows($actor, $permission);
    }
}
