<?php

namespace App\Policies\Central;

use App\Models\Central\User;
use App\Services\Central\CentralUserAccess;

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
        if ($target->roles()->where('guard_name', 'central')->where('is_super_admin', true)->exists()
            && ! $this->access->isSuperAdmin($actor)) {
            return false;
        }
        if ($this->access->isStaff($target)) {
            return $this->access->allows($actor, 'saas.staff.invite', $target)
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
        return ! $target->roles()->where('guard_name', 'central')->where('is_super_admin', true)->exists()
            && ($actor->id !== $target->id || $this->access->isSuperAdmin($actor))
            && $this->access->allows($actor, 'saas.staff.invite', $target)
            && $this->access->allows($actor, 'saas.users.roles.assign', $target);
    }
}
