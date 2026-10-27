<?php

namespace App\Policies\Central;

use App\Models\Central\Permissions\Permission;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Roles\CentralRoleCatalog;

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

    public function delegate(User $actor, Permission $permission): bool
    {
        return $permission->guard_name === 'central'
            && isset(CentralRoleCatalog::PERMISSIONS[$permission->name])
            && $this->access->ownsPermission($actor, $permission->name);
    }
}
