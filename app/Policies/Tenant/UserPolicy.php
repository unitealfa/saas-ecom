<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;

final class UserPolicy
{
    public function viewAny(User $actor): bool { return TenantAccess::allows($actor, 'shop.users.view'); }
    public function view(User $actor, User $member): bool { return $this->viewAny($actor); }
    public function manageMembership(User $actor, User $member): bool
    {
        return TenantAccess::allows($actor, 'shop.team.memberships.update')
            && $actor->id !== $member->id && TenantAccess::editableEmployee($member);
    }
    public function manageRoles(User $actor, User $member): bool
    {
        return TenantAccess::allows($actor, 'shop.roles.assign')
            && $actor->id !== $member->id && TenantAccess::editableEmployee($member);
    }
    public function managePermissions(User $actor, User $member): bool
    {
        return TenantAccess::allows($actor, 'shop.users.permissions.update')
            && $actor->id !== $member->id && TenantAccess::editableEmployee($member);
    }
    public function renewPermission(User $actor, User $member): bool
    {
        return TenantAccess::allows($actor, 'shop.users.permissions.renew')
            && $actor->id !== $member->id && TenantAccess::editableEmployee($member);
    }
}
