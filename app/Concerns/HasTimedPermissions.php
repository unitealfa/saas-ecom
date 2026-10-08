<?php

namespace App\Concerns;

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Users\StatusEnum;
use App\Models\PermissionAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

trait HasTimedPermissions
{
    use HasRoles {
        assignRole as private assignPackageRole;
        givePermissionTo as private givePackagePermissionTo;
    }

    public function assignRole(mixed ...$roles): static
    {
        return $this->getConnection()->transaction(function () use ($roles): static {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $requested = collect($roles)->flatten()->map(function (mixed $role): Role {
                $stored = $this->getStoredRole($role);
                if (! $stored instanceof Role) {
                    throw new \LogicException('Configured roles must extend the Spatie role model.');
                }

                return $stored;
            });
            $all = $this->roles()->get()->merge($requested)->unique('id');
            $seen = [];
            foreach ($all as $role) {
                $permissionIds = $role->permissions()->pluck('permissions.id');
                if ($permissionIds->isEmpty() && ! $role->getAttribute('is_super_admin')) {
                    throw new \LogicException('An empty role cannot be assigned.');
                }
                foreach ($permissionIds as $permissionId) {
                    if (in_array($permissionId, $seen, true)) {
                        throw new \LogicException('Assigned roles cannot have a permission in common.');
                    }
                    $seen[] = $permissionId;
                }
            }
            if ($all->count() > 1 && $all->contains(fn ($role): bool => (bool) $role->getAttribute('is_super_admin'))) {
                throw new \LogicException('A super administrator role cannot be combined with another role.');
            }
            if ($this->permissions()->whereIn('permissions.id', $seen)->exists()
                || ($all->contains(fn ($role): bool => (bool) $role->getAttribute('is_super_admin')) && $this->permissions()->exists())) {
                throw new \LogicException('A role cannot overlap with direct permissions.');
            }
            $this->unsetRelation('roles');
            $this->assignPackageRole(...$roles);
            activity()->performedOn($this)->event('roles_assigned')
                ->withProperties(['role_uuids' => $requested->pluck('uuid')->all()])->log('Roles assigned');

            return $this;
        });
    }

    public function syncRoles(mixed ...$roles): static
    {
        return $this->getConnection()->transaction(function () use ($roles): static {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $roleIds = $this->collectRoles($roles);
            $removed = $this->roles()->whereNotIn('roles.id', $roleIds)->get();
            if ($removed->isNotEmpty()) {
                $this->removeRole($removed);
            }

            return $this->assignRole(...$roles);
        });
    }

    public function givePermissionTo(mixed ...$permissions): static
    {
        return $this->getConnection()->transaction(function () use ($permissions): static {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $permissionIds = $this->collectPermissions($permissions);
            foreach ($this->roles()->get() as $role) {
                if ($role->getAttribute('is_super_admin') || $role->permissions()->whereKey($permissionIds)->exists()) {
                    throw new \LogicException('A direct permission cannot overlap with an assigned role.');
                }
            }
            $this->unsetRelation('permissions');
            $this->givePackagePermissionTo(...$permissions);

            return $this;
        });
    }

    public function syncPermissions(mixed ...$permissions): static
    {
        return $this->getConnection()->transaction(function () use ($permissions): static {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $permissionIds = $this->collectPermissions($permissions);
            $removed = $this->permissions()->whereNotIn('permissions.id', $permissionIds)->get();
            if ($removed->isNotEmpty()) {
                $this->revokePermissionTo($removed);
            }

            return $this->givePermissionTo(...$permissions);
        });
    }

    /** @return class-string<Role> */
    public function getRoleClass(): string
    {
        return $this->guard_name === 'central' ? \App\Models\Central\Role::class : \App\Models\Tenant\Role::class;
    }

    /** @return class-string<Permission> */
    public function getPermissionClass(): string
    {
        return $this->guard_name === 'central' ? \App\Models\Central\Permission::class : \App\Models\Tenant\Permission::class;
    }

    /** @return BelongsToMany<Role, $this, PermissionAssignment> */
    public function roles(): BelongsToMany
    {
        return $this->morphToMany($this->getRoleClass(), 'model', config('permission.table_names.model_has_roles'), 'model_id', 'role_id')
            ->using(PermissionAssignment::class)->withPivot('assigned_at');
    }

    /** @return BelongsToMany<Permission, $this, PermissionAssignment> */
    public function permissions(): BelongsToMany
    {
        return $this->morphToMany($this->getPermissionClass(), 'model', config('permission.table_names.model_has_permissions'), 'model_id', 'permission_id')
            ->using(PermissionAssignment::class)->withPivot('assigned_at', 'expires_at');
    }

    public function hasPermissionTo(mixed $permission, ?string $guardName = null): bool
    {
        if ($this->getAttribute('status') !== StatusEnum::ACTIVE || $this->getAttribute('deleted_at') !== null) {
            return false;
        }
        if ($this->getMorphClass() === 'shop_user' && $this->getAttribute('membership_status') !== MembershipStatusEnum::ACTIVE) {
            return false;
        }
        $permission = $this->filterPermission($permission, $guardName);
        $now = now();
        if ($this->permissions()->whereKey($permission->getKey())->wherePivot('assigned_at', '<=', $now)->wherePivot('expires_at', '>', $now)->exists()) {
            return true;
        }
        foreach ($this->roles()->wherePivot('assigned_at', '<=', $now)->get() as $role) {
            if ($role->getAttribute('is_super_admin')) {
                return true;
            }
            $grant = $role->permissions()->whereKey($permission->getKey())->first();
            if ($grant !== null && CarbonImmutable::parse($role->getRelation('pivot')->getAttribute('assigned_at'))->addDays((int) $grant->getRelation('pivot')->getAttribute('duration_days'))->isAfter($now)) {
                return true;
            }
        }

        return false;
    }
}
