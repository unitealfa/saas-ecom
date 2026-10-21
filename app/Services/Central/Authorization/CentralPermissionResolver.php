<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Permission;
use App\Models\Central\User;
use App\Services\Central\Roles\CentralRoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class CentralPermissionResolver
{
    /** @return Collection<int, array{uuid: string, label: string, code: string, editable: bool, direct: bool, days: int, effective: bool, visible: bool, sources: list<array{label: string, assigned_at: CarbonImmutable, expires_at: CarbonImmutable|null}>}> */
    public function forStaff(User $actor, User $target): Collection
    {
        Gate::forUser($actor)->authorize('viewStaffPermissions', $target);
        $target = User::withTrashed()->with(['roles.permissions', 'permissions'])->findOrFail($target->id);
        $now = now();
        $roles = $target->roles;
        $root = $roles->first(fn ($role): bool => $role->getAttribute('is_super_admin') && $role->pivot->assigned_at <= $now);
        $canUpdate = Gate::forUser($actor)->allows('updateStaffPermissions', $target);
        $assignedIds = $target->getAllPermissions()->modelKeys();
        $permissions = Permission::forCentralGuard()->when($root === null, function ($query) use ($assignedIds, $canUpdate): void {
            $query->where(function ($query) use ($assignedIds, $canUpdate): void {
                $query->whereIn('id', $assignedIds);
                if ($canUpdate) {
                    $query->orWhereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS));
                }
            });
        })->orderBy('name')->get();

        return $permissions->map(function ($permission) use ($actor, $target, $roles, $root, $canUpdate): array {
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
                $expiresAt = $assignedAt->addDays((int) $grant->pivot->duration_days);
                $sources[] = ['label' => $role->label, 'assigned_at' => $assignedAt, 'expires_at' => $expiresAt];
            }
            $direct = $target->permissions->firstWhere('id', $permission->id);
            if ($direct !== null) {
                $sources[] = ['label' => 'Attribution directe Spatie', 'assigned_at' => $direct->pivot->assigned_at, 'expires_at' => $direct->pivot->expires_at];
            }

            $editable = $canUpdate && Gate::forUser($actor)->allows('delegate', $permission);

            return ['uuid' => $permission->uuid, 'label' => $permission->label, 'code' => $permission->name,
                'visible' => ! $canUpdate || $sources !== [] || $editable,
                'editable' => $editable && $roles->every(fn ($role) => $role->usesIndividualPermissions() || ! $role->permissions->contains('id', $permission->id)),
                'direct' => $direct !== null,
                'days' => $direct === null ? 9999 : (int) $direct->pivot->assigned_at->diffInDays($direct->pivot->expires_at),
                'effective' => Gate::forUser($target)->allows($permission->name), 'sources' => $sources];
        })->filter(fn (array $row): bool => $row['visible'])->values();
    }
}
