<?php

namespace App\Services\Central\Authorization;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Users\User;
use App\Services\Central\Roles\CentralRoleCatalog;
use Illuminate\Support\Collection;

class CentralUserAccess
{
    private const string GUARD = 'central';

    private const string ROOT_ROLE_NAME = 'root';

    public function isSuperAdmin(User $user): bool
    {
        $active = $this->activeUser($user);

        return $active !== null && $this->hasSuperAdminRole($active);
    }

    public function allows(User $actor, string $ability): bool
    {
        $actor = $this->activeUser($actor);

        return $actor !== null && $this->hasAbility($actor, $ability);
    }

    public function ownsRolePermissions(User $actor, Role $role): bool
    {
        if ($role->guard_name !== self::GUARD || $role->getAttribute('is_super_admin')) {
            return false;
        }

        $actor = $this->activeUser($actor);

        return $actor !== null
            && $role->permissionComposition->every(fn ($permission): bool => $this->hasPermission($actor, $permission->name));
    }

    public function ownsPermission(User $actor, string $permission): bool
    {
        $actor = $this->activeUser($actor);

        return $actor !== null && $this->hasPermission($actor, $permission);
    }

    /**
     * Lecture groupée pour le formulaire ; les écritures conservent leurs contrôles actuels.
     *
     * @return Collection<int, Permission>
     */
    public function ownedPermissions(User $actor): Collection
    {
        $actor = $this->activeUser($actor);
        if ($actor === null) {
            return collect();
        }

        $roles = $actor->roles()->where('guard_name', self::GUARD)->get();
        if ($roles->isEmpty()) {
            return collect();
        }

        $permissions = Permission::forCentralGuard()
            ->whereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS))->orderBy('id');
        $now = now();
        if ($roles->contains(fn (Role $role): bool => $role->is_super_admin && $role->pivot->assigned_at <= $now)) {
            return $permissions->get();
        }

        if (! config('permission.central_profiles', false)) {
            return $permissions->get()->filter(fn (Permission $permission): bool => $this->hasPermission($actor, $permission->name));
        }

        $ids = $actor->permissions()->wherePivot('assigned_at', '<=', $now)
            ->wherePivot('expires_at', '>', $now)->pluck('permissions.id');

        return $permissions->whereIn('id', $ids)->get();
    }

    public function isStaff(User $user): bool
    {
        if ($user->canReuseAuthorizationRelations()) {
            $user->loadMissing('roles');
        }
        if ($user->relationLoaded('roles')) {
            return $user->roles->contains(fn ($role): bool => $role->guard_name === self::GUARD);
        }

        return $user->roles()->where('guard_name', self::GUARD)->exists();
    }

    public function mayManageStaff(User $actor, User $target): bool
    {
        $actor = $this->activeUser($actor);

        if ($actor === null) {
            return false;
        }

        if ($actor->id === $target->id || $this->hasSuperAdminRole($actor)) {
            return true;
        }

        if ($this->hasSuperAdminRole($target)) {
            return false;
        }

        return $target->getAllPermissions()
            ->every(fn ($permission): bool => $this->hasPermission($actor, $permission->name));
    }

    private function activeUser(User $user): ?User
    {
        if (tenancy()->initialized) {
            return null;
        }

        $fresh = $user->canReuseAuthorizationRelations() ? $user : $user->fresh();

        return $fresh !== null && $fresh->status === StatusEnum::ACTIVE && ! $fresh->trashed() ? $fresh : null;
    }

    private function hasSuperAdminRole(User $user): bool
    {
        if ($user->canReuseAuthorizationRelations()) {
            $user->loadMissing('roles');

            return $user->roles->contains(fn (Role $role): bool => $role->guard_name === self::GUARD
                && $role->name === self::ROOT_ROLE_NAME && $role->is_super_admin
                && $role->is_system && $role->is_protected && $role->pivot->assigned_at <= now());
        }

        return $user->roles()->where('guard_name', self::GUARD)
            ->where('name', self::ROOT_ROLE_NAME)
            ->where('is_super_admin', true)
            ->where('is_system', true)
            ->where('is_protected', true)
            ->wherePivot('assigned_at', '<=', now())->exists();
    }

    private function hasAbility(User $actor, string $ability): bool
    {
        if ($this->hasPermission($actor, $ability)) {
            return true;
        }

        if (config('permission.central_profiles', false)) {
            return false;
        }

        foreach (CentralRoleCatalog::VIEW_ACTIONS[$ability] ?? [] as $action) {
            if ($this->hasAbility($actor, $action)) {
                return true;
            }
        }

        return false;
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $actor->checkPermissionTo($permission, self::GUARD);
    }
}
