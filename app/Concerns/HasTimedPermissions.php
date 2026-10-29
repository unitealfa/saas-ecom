<?php

namespace App\Concerns;

use App\Enums\Central\Users\StatusEnum as CentralUserStatus;
use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Shared\Authorization\PermissionAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @template TRole of Role
 * @template TPermission of Permission
 *
 * @property-read EloquentCollection<int, TRole> $roles
 * @property-read EloquentCollection<int, TPermission> $permissions
 */
trait HasTimedPermissions
{
    use HasRoles {
        assignRole as private assignPackageRole;
        givePermissionTo as private givePackagePermissionTo;
        getPermissionsViaRoles as private getPackagePermissionsViaRoles;
        hasPermissionViaRole as private hasPackagePermissionViaRole;
    }

    public const string AUTHORIZATION_READ_USER = 'aydra.authorization.read_user';

    public function canReuseAuthorizationRelations(): bool
    {
        $request = app()->bound('request') ? app('request') : null;

        return $this->getMorphClass() === 'central_user'
            && ! tenancy()->initialized
            && $request instanceof Request
            && in_array($request->method(), ['GET', 'HEAD'], true)
            && $request->attributes->get(self::AUTHORIZATION_READ_USER) === $this
            && $this->getConnection()->transactionLevel() === 0;
    }

    public function assignRole(mixed ...$roles): static
    {
        return $this->getConnection()->transaction(function () use ($roles): static {
            $this->newQuery()->withTrashed()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $roleClass = $this->getRoleClass();
            /** @var Collection<int, TRole> $requested */
            $requested = collect($roles)->flatten()->map(function (mixed $role) use ($roleClass): Role {
                $stored = $this->getStoredRole($role);
                if (! $stored instanceof $roleClass) {
                    throw new \LogicException('Configured roles must use the current authorization model.');
                }

                return $stored;
            });
            $all = $this->roles()->get()->merge($requested)->unique('id');
            $previousRoleIds = $this->roles()->pluck('roles.id');
            $seen = [];
            foreach ($all as $role) {
                $permissionIds = $role->usesIndividualPermissions() ? collect() : $role->permissions()->pluck('permissions.id');
                if ($role->permissionComposition()->count() === 0 && ! $role->getAttribute('is_super_admin')) {
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
            $initialGrants = [];
            foreach ($requested->unique('id') as $role) {
                if (! $role->usesIndividualPermissions() || $role->getAttribute('is_super_admin') || $previousRoleIds->contains($role->id)) {
                    continue;
                }
                $assignedAt = $this->roles()->whereKey($role->id)->firstOrFail()->pivot->assigned_at;
                foreach ($role->permissionComposition()->get() as $permission) {
                    if ($this->permissions()->whereKey($permission->id)->exists()) {
                        continue;
                    }
                    $this->givePermissionTo($permission);
                    $this->permissions()->updateExistingPivot($permission->id, [
                        'assigned_at' => $assignedAt,
                        'expires_at' => $assignedAt->addDays((int) $permission->getRelation('pivot')->getAttribute('duration_days')),
                    ]);
                    $initialGrants[] = ['permission_uuid' => $permission->uuid,
                        'assigned_at' => $assignedAt->toIso8601String(),
                        'expires_at' => $assignedAt->addDays((int) $permission->getRelation('pivot')->getAttribute('duration_days'))->toIso8601String()];
                }
            }
            activity()->performedOn($this)->event('roles_assigned')
                ->withProperties(['role_uuids' => $requested->pluck('uuid')->all(), 'initial_direct_permissions' => $initialGrants])->log('Roles assigned');

            return $this;
        });
    }

    public function syncRoles(mixed ...$roles): static
    {
        return $this->getConnection()->transaction(function () use ($roles): static {
            $this->newQuery()->withTrashed()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
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
            $this->newQuery()->withTrashed()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $permissionIds = $this->collectPermissions($permissions);
            if ($permissionIds !== [] && $this->getMorphClass() === 'central_user'
                && ! $this->roles()->where('guard_name', 'central')->exists()) {
                throw new \LogicException('A merchant account cannot receive SaaS permissions.');
            }
            foreach ($this->roles()->get() as $role) {
                if ($role->getAttribute('is_super_admin')
                    || (! $role->usesIndividualPermissions() && $role->permissions()->whereKey($permissionIds)->exists())) {
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
            $this->newQuery()->withTrashed()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $permissionIds = $this->collectPermissions($permissions);
            $removed = $this->permissions()->whereNotIn('permissions.id', $permissionIds)->get();
            if ($removed->isNotEmpty()) {
                $this->revokePermissionTo($removed);
            }

            return $this->givePermissionTo(...$permissions);
        });
    }

    /** @return class-string<TRole> */
    public function getRoleClass(): string
    {
        /** @var class-string<TRole> $model */
        $model = $this->guard_name === 'central' ? \App\Models\Central\Roles\Role::class : \App\Models\Tenant\Roles\Role::class;

        return $model;
    }

    /** @return class-string<TPermission> */
    public function getPermissionClass(): string
    {
        /** @var class-string<TPermission> $model */
        $model = $this->guard_name === 'central' ? \App\Models\Central\Permissions\Permission::class : \App\Models\Tenant\Permissions\Permission::class;

        return $model;
    }

    /** @return BelongsToMany<TRole, $this, PermissionAssignment> */
    public function roles(): BelongsToMany
    {
        return $this->morphToMany($this->getRoleClass(), 'model', config('permission.table_names.model_has_roles'), 'model_id', 'role_id')
            ->using(PermissionAssignment::class)->withPivot('assigned_at');
    }

    /** @return BelongsToMany<TPermission, $this, PermissionAssignment> */
    public function permissions(): BelongsToMany
    {
        return $this->morphToMany($this->getPermissionClass(), 'model', config('permission.table_names.model_has_permissions'), 'model_id', 'permission_id')
            ->using(PermissionAssignment::class)->withPivot('assigned_at', 'expires_at');
    }

    public function hasPermissionTo(mixed $permission, ?string $guardName = null): bool
    {
        $activeStatus = $this->guard_name === 'central' ? CentralUserStatus::ACTIVE : StatusEnum::ACTIVE;
        if ($this->getAttribute('status') !== $activeStatus || $this->getAttribute('deleted_at') !== null) {
            return false;
        }
        if ($this->getMorphClass() === 'shop_user' && $this->getAttribute('membership_status') !== MembershipStatusEnum::ACTIVE) {
            return false;
        }
        $reuseRelations = $this->canReuseAuthorizationRelations();
        if ($reuseRelations) {
            $this->loadMissing(['roles', 'permissions']);
        }
        if ($this->getMorphClass() === 'central_user' && ! ($reuseRelations
            ? $this->roles->contains(fn ($role): bool => $role->guard_name === 'central')
            : $this->roles()->where('guard_name', 'central')->exists())) {
            return false;
        }
        $permission = $this->filterPermission($permission, $guardName);
        $now = now();
        $directPermission = $reuseRelations
            ? $this->permissions->contains(fn ($grant): bool => $grant->getKey() === $permission->getKey()
                && $grant->pivot->assigned_at <= $now && $grant->pivot->expires_at > $now)
            : $this->permissions()->whereKey($permission->getKey())->wherePivot('assigned_at', '<=', $now)->wherePivot('expires_at', '>', $now)->exists();
        if ($directPermission) {
            return true;
        }
        $roles = $reuseRelations
            ? $this->roles->filter(fn ($role): bool => $role->pivot->assigned_at <= $now)
            : $this->roles()->wherePivot('assigned_at', '<=', $now)->get();
        foreach ($roles as $role) {
            if ($role->getAttribute('is_super_admin')) {
                return true;
            }
            if ($role->usesIndividualPermissions()) {
                continue;
            }
            $grant = $role->permissions()->whereKey($permission->getKey())->first();
            if ($grant !== null && CarbonImmutable::parse($role->getRelation('pivot')->getAttribute('assigned_at'))->addDays((int) $grant->getRelation('pivot')->getAttribute('duration_days'))->isAfter($now)) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, Permission> */
    public function getPermissionsViaRoles(): Collection
    {
        if ($this->guard_name === 'central' && config('permission.central_profiles', false)) {
            return collect();
        }

        return $this->getPackagePermissionsViaRoles();
    }

    protected function hasPermissionViaRole(\Spatie\Permission\Contracts\Permission $permission): bool
    {
        if ($this->guard_name === 'central' && config('permission.central_profiles', false)) {
            return false;
        }

        return $this->hasPackagePermissionViaRole($permission);
    }
}
