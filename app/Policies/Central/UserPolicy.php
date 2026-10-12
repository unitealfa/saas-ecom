<?php

namespace App\Policies\Central;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;

class UserPolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.users.view', listing: true);
    }

    public function view(User $actor, User $target): bool
    {
        return $this->access->allows($actor, 'saas.users.view', $target);
    }

    public function create(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.users.create');
    }

    public function update(User $actor, User $target): bool
    {
        if ($target->trashed()) {
            return false;
        }
        if ($target->roles()->where('guard_name', 'central')->where('is_super_admin', true)->exists()
            && ! $this->access->isSuperAdmin($actor)) {
            return false;
        }
        if ($this->access->isStaff($target)) {
            return ($this->access->allows($actor, 'saas.users.update', $target)
                || $this->access->allows($actor, 'saas.staff.update', $target))
                && $this->access->mayManageStaff($actor, $target);
        }

        return $this->access->allows($actor, 'saas.users.update', $target);
    }

    public function createStaff(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.staff.invite')
            && $this->access->allows($actor, 'saas.users.roles.assign');
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return ! $target->trashed() && ! $target->roles()->where('guard_name', 'central')->where('is_super_admin', true)->exists()
            && ! in_array($target->status, [StatusEnum::PENDING, StatusEnum::REJECTED], true)
            && ($actor->id !== $target->id || $this->access->isSuperAdmin($actor))
            && $this->access->allows($actor, 'saas.staff.invite', $target)
            && $this->access->allows($actor, 'saas.users.roles.assign', $target);
    }

    public function suspend(User $actor, User $target): bool
    {
        return $target->status === StatusEnum::ACTIVE && $this->manageState($actor, $target, 'suspend');
    }

    public function approve(User $actor, User $target): bool
    {
        return $this->decideRegistration($actor, $target, 'approve');
    }

    public function reject(User $actor, User $target): bool
    {
        return $this->decideRegistration($actor, $target, 'reject');
    }

    private function decideRegistration(User $actor, User $target, string $action): bool
    {
        return ! $target->trashed() && $actor->id !== $target->id
            && $target->status === StatusEnum::PENDING && ! $this->access->isStaff($target)
            && $this->access->allows($actor, 'saas.users.'.$action);
    }

    public function activate(User $actor, User $target): bool
    {
        return in_array($target->status, [StatusEnum::INACTIVE, StatusEnum::SUSPENDED], true)
            && $this->manageState($actor, $target, 'activate');
    }

    public function archive(User $actor, User $target): bool
    {
        return $this->manageState($actor, $target, 'archive');
    }

    public function viewLegal(User $actor, User $target): bool
    {
        return ! $this->access->isStaff($target) && $this->access->allows($actor, 'saas.users.legal.view');
    }

    public function validateLegal(User $actor, User $target): bool
    {
        return ! $target->trashed() && $actor->id !== $target->id && ! $this->access->isStaff($target)
            && $this->access->allows($actor, 'saas.users.legal.validate');
    }

    public function rejectLegal(User $actor, User $target): bool
    {
        return ! $target->trashed() && $actor->id !== $target->id && ! $this->access->isStaff($target)
            && $this->access->allows($actor, 'saas.users.legal.reject');
    }

    public function viewHistory(User $actor, User $target): bool
    {
        return $this->access->allows($actor, 'saas.users.history.view');
    }

    private function manageState(User $actor, User $target, string $action): bool
    {
        if ($target->trashed() || $actor->id === $target->id || $target->roles()->where('guard_name', 'central')->where('is_super_admin', true)->exists()) {
            return false;
        }
        $staff = $this->access->isStaff($target);

        $legacy = ['activate' => 'restore', 'archive' => 'delete'][$action] ?? $action;

        return ($this->access->allows($actor, 'saas.users.'.$action, $target)
                || ($legacy !== $action && $this->access->allows($actor, 'saas.users.'.$legacy, $target))
                || ($staff && $this->access->allows($actor, 'saas.staff.'.$legacy, $target)))
            && (! $staff || $this->access->mayManageStaff($actor, $target));
    }
}
