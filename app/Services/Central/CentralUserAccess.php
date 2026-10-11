<?php

namespace App\Services\Central;

use App\Enums\Users\StatusEnum;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\Central\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
        if ($actor === null || ! $this->active($actor) || ! $actor->checkPermissionTo($ability, 'central')) {
            return false;
        }
        $restrictions = $this->restrictions($actor, $ability);
        if ($restrictions->contains(fn (\stdClass $rule): bool => (int) $rule->effect === 1 && (
            $rule->normalized_target_type === 'global'
            || ($target !== null && $rule->target_user_id !== null && (int) $rule->target_user_id === $target->id)
        ))) {
            return false;
        }
        $allowOnly = $restrictions->where('effect', 2);
        if ($allowOnly->isEmpty() || $allowOnly->contains('normalized_target_type', 'global')) {
            return true;
        }

        return $listing
            ? $allowOnly->contains(fn (\stdClass $rule): bool => $rule->target_user_id !== null)
            : $target !== null && $allowOnly->contains(fn (\stdClass $rule): bool => $rule->target_user_id !== null && (int) $rule->target_user_id === $target->id);
    }

    public function mayDelegateRole(User $actor, Role $role): bool
    {
        return ! $this->restrictions($actor, 'saas.users.roles.assign')->contains(
            fn (\stdClass $rule): bool => (int) $rule->effect === 1
                && ($rule->normalized_target_type === 'global'
                    || ($rule->target_role_id !== null && (int) $rule->target_role_id === $role->id)),
        );
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
            || $target->getAllPermissions()->every(fn ($permission): bool => $this->allows($actor, $permission->name));
    }

    /** @param Builder<\App\Models\User> $query */
    public function scopeVisibleUsers(Builder $query, User $actor): void
    {
        $restrictions = $this->restrictions($actor, 'saas.users.view');
        if ($restrictions->contains(fn (\stdClass $rule): bool => (int) $rule->effect === 1 && $rule->normalized_target_type === 'global')) {
            $query->whereRaw('1 = 0');

            return;
        }
        $denied = $restrictions->where('effect', 1)->pluck('target_user_id')->filter()->all();
        $query->whereNotIn('users.id', $denied);
        $allowOnly = $restrictions->where('effect', 2);
        if ($allowOnly->isNotEmpty() && ! $allowOnly->contains('normalized_target_type', 'global')) {
            $query->whereIn('users.id', $allowOnly->pluck('target_user_id')->filter()->all());
        }
    }

    /** @return Collection<int, \stdClass> */
    private function restrictions(User $actor, string $ability): Collection
    {
        $permission = Permission::where('guard_name', 'central')->where('name', $ability)->first();
        if ($permission === null) {
            return collect();
        }

        return $actor->getConnection()->table('admin_restrictions')
            ->where('admin_id', $actor->id)->where('permission_id', $permission->id)
            ->where('status', 1)->whereNull('deleted_at')->where('started_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();
    }

    private function active(User $user): bool
    {
        return ! tenancy()->initialized && $user->status === StatusEnum::ACTIVE && ! $user->trashed();
    }
}
