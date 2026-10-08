<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasPermissions;

trait HasRoleMetadata
{
    use HasPermissions {
        givePermissionTo as private givePackagePermissionTo;
        revokePermissionTo as private revokePackagePermissionTo;
    }

    /** @param array<string, int> $permissionDurations */
    public static function createWithPermissions(string $name, array $permissionDurations): static
    {
        if ($permissionDurations === []) {
            throw new \InvalidArgumentException('A delegable role must contain at least one permission.');
        }
        $role = static::query()->newModelInstance(['name' => $name]);
        $permissionClass = $role->getAttribute('guard_name') === 'central' ? \App\Models\Central\Permission::class : \App\Models\Tenant\Permission::class;

        return $role->getConnection()->transaction(function () use ($role, $permissionClass, $permissionDurations): static {
            $grants = [];
            foreach ($permissionDurations as $permissionName => $days) {
                if ($days < 1 || $days > 9999) {
                    throw new \InvalidArgumentException('Permission duration must be between 1 and 9999 days.');
                }
                $permission = $permissionClass::findByName($permissionName, $role->getAttribute('guard_name'));
                $grants[(int) $permission->getKey()] = ['duration_days' => $days];
            }
            ksort($grants);
            $parts = [];
            foreach ($grants as $id => $grant) {
                $parts[] = [$id, $grant['duration_days']];
            }
            $role->setAttribute('permission_signature', hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR)));
            $role->save();
            $role->permissions()->attach($grants);
            $role->forgetCachedPermissions();

            return $role;
        });
    }

    public static function bootHasRoleMetadata(): void
    {
        static::creating(function (Model $role): void {
            $role->setAttribute('label', $role->getAttribute('label') ?? $role->getAttribute('name'));
            $role->setAttribute('permission_signature', $role->getAttribute('permission_signature') ?? hash('sha256', '[]'));
        });
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return parent::permissions()->withPivot('duration_days');
    }

    /** @param string|int|Permission|\BackedEnum|array<array-key, mixed>|Collection<array-key, mixed> ...$permissions */
    public function givePermissionTo(mixed ...$permissions): static
    {
        return $this->getConnection()->transaction(function () use ($permissions): static {
            $this->givePackagePermissionTo(...$permissions);
            $this->refreshPermissionSignature();

            return $this;
        });
    }

    /** @param string|int|Permission|\BackedEnum|array<array-key, mixed>|Collection<array-key, mixed> ...$permissions */
    public function syncPermissions(mixed ...$permissions): static
    {
        return $this->getConnection()->transaction(function () use ($permissions): static {
            $permissionIds = $this->collectPermissions($permissions);
            $removed = $this->permissions()->whereNotIn('permissions.id', $permissionIds)->get();
            if ($removed->isNotEmpty()) {
                $this->revokePackagePermissionTo($removed);
            }
            $this->unsetRelation('permissions');
            $this->givePackagePermissionTo(...$permissions);
            $this->refreshPermissionSignature();

            return $this;
        });
    }

    public function revokePermissionTo(mixed $permission): static
    {
        return $this->getConnection()->transaction(function () use ($permission): static {
            $this->revokePackagePermissionTo($permission);
            $this->refreshPermissionSignature();

            return $this;
        });
    }

    public function setPermissionDuration(string $permissionName, int $days): void
    {
        if ($days < 1 || $days > 9999) {
            throw new \InvalidArgumentException('Permission duration must be between 1 and 9999 days.');
        }
        $this->getConnection()->transaction(function () use ($permissionName, $days): void {
            $permission = $this->permissions()->where('name', $permissionName)->firstOrFail();
            $this->permissions()->updateExistingPivot($permission->getKey(), ['duration_days' => $days]);
            $this->refreshPermissionSignature();
        });
    }

    private function refreshPermissionSignature(): void
    {
        $parts = $this->permissions()->orderBy('permissions.id')->get()
            ->map(fn (Model $permission): array => [(int) $permission->getKey(), (int) $permission->getRelation('pivot')->getAttribute('duration_days')])->all();
        $this->assertNoAssignedPermissionOverlap(array_column($parts, 0));
        $this->setAttribute('permission_signature', hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR)));
        $this->setAttribute('permission_version', ((int) $this->getAttribute('permission_version')) + 1);
        $this->save();
        $this->unsetRelation('permissions');
        $this->forgetCachedPermissions();
    }

    /** @param list<int> $permissionIds */
    private function assertNoAssignedPermissionOverlap(array $permissionIds): void
    {
        $assignments = config('permission.table_names.model_has_roles');
        $rolePermissions = config('permission.table_names.role_has_permissions');
        $directPermissions = config('permission.table_names.model_has_permissions');
        $recipients = $this->getConnection()->table($assignments)->where('role_id', $this->getKey())->get();

        foreach ($recipients as $recipient) {
            $roleOverlap = $this->getConnection()->table($assignments.' as assignment')
                ->join($rolePermissions.' as grant', 'grant.role_id', '=', 'assignment.role_id')
                ->where('assignment.model_type', $recipient->model_type)->where('assignment.model_id', $recipient->model_id)
                ->where('assignment.role_id', '<>', $this->getKey())->whereIn('grant.permission_id', $permissionIds)->exists();
            $directOverlap = $this->getConnection()->table($directPermissions)
                ->where('model_type', $recipient->model_type)->where('model_id', $recipient->model_id)
                ->whereIn('permission_id', $permissionIds)->exists();
            if ($roleOverlap || $directOverlap) {
                throw new \LogicException('The new role composition overlaps with an existing assignment.');
            }
        }
    }
}
