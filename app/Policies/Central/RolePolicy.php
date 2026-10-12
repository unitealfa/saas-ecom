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
        return $this->access->isSuperAdmin($actor);
    }

    public function create(User $actor): bool
    {
        return $this->access->isSuperAdmin($actor);
    }

    public function update(User $actor, Role $role): bool
    {
        return $this->access->isSuperAdmin($actor) && $role->guard_name === 'central'
            && ! $role->getAttribute('is_protected') && ! $role->getAttribute('is_system')
            && ! $role->getAttribute('is_super_admin');
    }
}
