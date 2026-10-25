<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\Roles\CentralRoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class CentralPermissionResolver
{
    private const int DEFAULT_DAYS = 9999;

    private const string DIRECT_SOURCE_LABEL = 'Attribution directe Spatie';

    public function forStaff(User $actor, User $target): Collection
    {
        Gate::forUser($actor)->authorize('viewStaffPermissions', $target);

        $target = User::withTrashed()->with(['roles.permissions', 'permissions'])->findOrFail($target->id);
        $roles = $target->roles;
        $root = $this->activeRootRole($roles);
        $canUpdate = Gate::forUser($actor)->allows('updateStaffPermissions', $target);

        return $this->listedPermissions($target, $root, $canUpdate)
            ->map(fn (Permission $permission): ?array => $this->row($actor, $target, $roles, $root, $canUpdate, $permission))
            ->filter()
            ->values();
    }

    private function activeRootRole(EloquentCollection $roles): ?Role
    {
        $now = now();

        return $roles->first(
            fn ($role): bool => $role->getAttribute('is_super_admin') && $role->pivot->assigned_at <= $now
        );
    }


    private function listedPermissions(User $target, ?Role $root, bool $canUpdate): EloquentCollection
    {
        $assignedIds = $target->getAllPermissions()->modelKeys();

        return Permission::forCentralGuard()
            ->when($root === null, function ($query) use ($assignedIds, $canUpdate): void {
                $query->where(function ($query) use ($assignedIds, $canUpdate): void {
                    $query->whereIn('id', $assignedIds);

                    if ($canUpdate) {
                        $query->orWhereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS));
                    }
                });
            })
            ->orderBy('name')
            ->get();
    }

    private function row(User $actor, User $target, EloquentCollection $roles, ?Role $root, bool $canUpdate, Permission $permission): ?array
    {
        $direct = $target->permissions->firstWhere('id', $permission->id);
        $sources = $this->sources($permission, $roles, $root, $direct);
        $delegable = $canUpdate && Gate::forUser($actor)->allows('delegate', $permission);

        if ($canUpdate && $sources === [] && ! $delegable) {
            return null;
        }

        return [
            'uuid' => $permission->uuid,
            'label' => $permission->label,
            'code' => $permission->name,
            'visible' => true,
            'editable' => $delegable && ! $this->grantedByRole($permission, $roles),
            'direct' => $direct !== null,
            'days' => $direct === null
                ? self::DEFAULT_DAYS
                : (int) $direct->pivot->assigned_at->diffInDays($direct->pivot->expires_at),
            'effective' => Gate::forUser($target)->allows($permission->name),
            'sources' => $sources,
        ];
    }

    private function sources(Permission $permission, EloquentCollection $roles, ?Role $root, ?Permission $direct): array
    {
        $sources = [];

        if ($root !== null) {
            $sources[] = ['label' => $root->label, 'assigned_at' => $root->pivot->assigned_at, 'expires_at' => null];
        }

        foreach ($roles as $role) {
            if ($role->usesIndividualPermissions()) {
                continue;
            }

            $grant = $role->permissions->firstWhere('id', $permission->id);

            if ($grant === null) {
                continue;
            }

            $assignedAt = $role->pivot->assigned_at;
            $sources[] = [
                'label' => $role->label,
                'assigned_at' => $assignedAt,
                'expires_at' => $assignedAt->addDays((int) $grant->pivot->duration_days),
            ];
        }

        if ($direct !== null) {
            $sources[] = [
                'label' => self::DIRECT_SOURCE_LABEL,
                'assigned_at' => $direct->pivot->assigned_at,
                'expires_at' => $direct->pivot->expires_at,
            ];
        }

        return $sources;
    }

    private function grantedByRole(Permission $permission, EloquentCollection $roles): bool
    {
        return $roles->contains(
            fn ($role): bool => ! $role->usesIndividualPermissions() && $role->permissions->contains('id', $permission->id)
        );
    }
}