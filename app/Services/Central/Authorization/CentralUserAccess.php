<?php

namespace App\Services\Central\Authorization;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\Roles\CentralRoleCatalog;

class CentralUserAccess
{
    public function isSuperAdmin(User $user): bool
    {
        $user = $user->fresh();
        if ($user === null || ! $this->active($user)) {
            return false;
        }

        return $user->roles()->where('guard_name', 'central')
            ->where('is_super_admin', true)->where('is_system', true)->where('is_protected', true)
            ->wherePivot('assigned_at', '<=', now())->exists();
    }

    public function allows(User $actor, string $ability, ?User $target = null, bool $listing = false): bool
    {
        $actor = $actor->fresh();
        if ($actor === null || ! $this->active($actor)) {
            return false;
        }
        if (! $actor->checkPermissionTo($ability, 'central')) {
            $derived = false;
            foreach (CentralRoleCatalog::VIEW_ACTIONS[$ability] ?? [] as $action) {
                if ($this->allows($actor, $action, $target, $listing)) {
                    $derived = true;
                    break;
                }
            }
            if (! $derived) {
                return false;
            }
        }

        return true;
    }

    public function mayDelegateRole(User $actor, Role $role): bool
    {
        return $this->allows($actor, 'saas.users.roles.assign') && $this->ownsRolePermissions($actor, $role);
    }

    public function ownsRolePermissions(User $actor, Role $role): bool
    {
        return $role->guard_name === 'central' && ! $role->getAttribute('is_super_admin')
            && $role->permissions->every(fn ($permission): bool => $this->ownsPermission($actor, $permission->name));
    }

    public function ownsPermission(User $actor, string $permission): bool
    {
        $actor = $actor->fresh();

        return $actor !== null && $this->active($actor) && $actor->checkPermissionTo($permission, 'central');
    }

    public function isStaff(User $user): bool
    {
        if ($user->relationLoaded('roles')) {
            return $user->roles->contains(fn ($role): bool => $role->guard_name === 'central');
        }

        return $user->roles()->where('guard_name', 'central')->exists();
    }

    public function mayManageStaff(User $actor, User $target): bool
    {
        return $this->isSuperAdmin($actor)
            || $target->getAllPermissions()->every(fn ($permission): bool => $this->ownsPermission($actor, $permission->name));
    }

    private function active(User $user): bool
    {
        return ! tenancy()->initialized && $user->status === StatusEnum::ACTIVE && ! $user->trashed();
    }
}
