<?php

namespace App\Policies\Central;

use App\Models\Central\Permission;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;

class PermissionPolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->access->isSuperAdmin($actor) || $this->access->allows($actor, 'saas.permissions.view');
    }

    public function view(User $actor, Permission $permission): bool
    {
        return $permission->guard_name === 'central' && $this->viewAny($actor);
    }

    public function update(User $actor, Permission $permission): bool
    {
        return $permission->guard_name === 'central'
            && ($this->access->isSuperAdmin($actor) || $this->access->allows($actor, 'saas.permissions.update'));
    }
}
