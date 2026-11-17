<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;

final class PermissionPolicy
{
    public function viewAny(User $actor): bool { return TenantAccess::allows($actor, 'shop.permissions.view'); }
    public function view(User $actor, Permission $permission): bool { return $this->viewAny($actor); }
}
