<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;

final class RolePolicy
{
    public function viewAny(User $actor): bool { return TenantAccess::allows($actor, 'shop.roles.view'); }
    public function view(User $actor, Role $role): bool { return $this->viewAny($actor); }
    public function create(User $actor): bool { return TenantAccess::allows($actor, 'shop.roles.create'); }
    public function update(User $actor, Role $role): bool
    {
        return TenantAccess::allows($actor, 'shop.roles.update') && ! $role->is_system && ! $role->is_protected && ! $role->is_super_admin;
    }
    public function delete(User $actor, Role $role): bool
    {
        return TenantAccess::allows($actor, 'shop.roles.delete') && ! $role->is_system && ! $role->is_protected && ! $role->is_super_admin;
    }
    public function assign(User $actor, Role $role): bool
    {
        return TenantAccess::allows($actor, 'shop.roles.assign') && ! $role->is_super_admin;
    }
}
