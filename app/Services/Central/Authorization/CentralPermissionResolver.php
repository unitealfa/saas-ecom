<?php

namespace App\Services\Central\Authorization;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Permission;
use App\Models\Central\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class CentralPermissionResolver
{
    /** @return Collection<int, array{label: string, code: string, effective: bool, sources: list<array{label: string, assigned_at: CarbonImmutable, expires_at: CarbonImmutable|null}>}> */
    public function forStaff(User $actor, User $target): Collection
    {
        Gate::forUser($actor)->authorize('viewStaffPermissions', $target);
        $target = User::withTrashed()->with(['roles.permissions', 'permissions'])->findOrFail($target->id);
        $active = ! $target->trashed() && $target->status === StatusEnum::ACTIVE;
        $now = now();
        $roles = $target->roles;
        $root = $roles->first(fn ($role): bool => $role->getAttribute('is_super_admin') && $role->pivot->assigned_at <= $now);
        $permissions = $root === null ? $target->getAllPermissions()->sortBy('name') : Permission::where('guard_name', 'central')->orderBy('name')->get();

        return $permissions->map(function ($permission) use ($target, $roles, $root, $active, $now): array {
            $sources = [];
            $effective = $active && $root !== null;
            if ($root !== null) {
                $sources[] = ['label' => $root->label, 'assigned_at' => $root->pivot->assigned_at, 'expires_at' => null];
            }
            foreach ($roles as $role) {
                $grant = $role->permissions->firstWhere('id', $permission->id);
                if ($grant === null) {
                    continue;
                }
                $assignedAt = $role->pivot->assigned_at;
                $expiresAt = $assignedAt->addDays((int) $grant->pivot->duration_days);
                $sources[] = ['label' => $role->label, 'assigned_at' => $assignedAt, 'expires_at' => $expiresAt];
                $effective = $effective || ($active && $assignedAt <= $now && $expiresAt > $now);
            }
            $direct = $target->permissions->firstWhere('id', $permission->id);
            if ($direct !== null) {
                $sources[] = ['label' => 'Attribution directe Spatie', 'assigned_at' => $direct->pivot->assigned_at, 'expires_at' => $direct->pivot->expires_at];
                $effective = $effective || ($active && $direct->pivot->assigned_at <= $now && $direct->pivot->expires_at > $now);
            }

            return ['label' => $permission->label, 'code' => $permission->name, 'effective' => $effective, 'sources' => $sources];
        })->values();
    }
}
