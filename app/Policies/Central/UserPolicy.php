<?php

namespace App\Policies\Central;

use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;

class UserPolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.users.view');
    }

    public function updateProfilePhoto(User $actor, User $target): bool
    {
        return $this->updateOwnProfile($actor, $target);
    }

    public function updateOwnProfile(User $actor, User $target): bool
    {
        $actor = $actor->fresh();

        return ! tenancy()->initialized && $actor !== null && $actor->status === StatusEnum::ACTIVE
            && ! $actor->trashed() && ! $target->trashed() && $actor->is($target) && $actor->uuid === $target->uuid;
    }

    public function viewProfilePhoto(User $actor, User $target): bool
    {
        return $this->updateProfilePhoto($actor, $target)
            || (! tenancy()->initialized && ($this->view($actor, $target) || $this->viewStaff($actor, $target)));
    }

    public function view(User $actor, User $target): bool
    {
        return $this->access->allows($actor, 'saas.users.view');
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
            return ($this->access->allows($actor, 'saas.users.update')
                || $this->access->allows($actor, 'saas.staff.update'))
                && $this->access->mayManageStaff($actor, $target);
        }

        return $this->access->allows($actor, 'saas.users.update');
    }

    public function createStaff(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.staff.invite')
            && $this->access->allows($actor, 'saas.users.roles.assign');
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return $this->access->isStaff($target) && ! $target->trashed()
            && ! $target->tenants()->withTrashed()->exists()
            && ! $target->roles()->where('guard_name', 'central')->where('is_super_admin', true)->exists()
            && ! in_array($target->status, [StatusEnum::PENDING, StatusEnum::REJECTED], true)
            && ($actor->id !== $target->id || $this->access->isSuperAdmin($actor))
            && $this->access->allows($actor, 'saas.users.roles.assign')
            && $this->access->mayManageStaff($actor, $target);
    }

    public function viewAnyStaff(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.staff.view');
    }

    public function viewStaff(User $actor, User $target): bool
    {
        return $this->access->isStaff($target) && $this->viewAnyStaff($actor);
    }

    public function updateStaff(User $actor, User $target): bool
    {
        return $this->access->isStaff($target)
            && $this->access->allows($actor, 'saas.staff.update') && $this->update($actor, $target);
    }

    public function assignStaffRoles(User $actor, User $target): bool
    {
        return $this->assignRoles($actor, $target);
    }

    public function viewStaffPermissions(User $actor, User $target): bool
    {
        return $this->access->isStaff($target) && ($this->access->allows($actor, 'saas.staff.permissions.view')
            || $this->updateStaffPermissions($actor, $target));
    }

    public function updateStaffPermissions(User $actor, User $target): bool
    {
        return (bool) config('permission.central_profiles', false)
            && ! in_array($target->status, [StatusEnum::PENDING, StatusEnum::REJECTED, StatusEnum::DELETED], true)
            && ! $target->tenants()->withTrashed()->exists()
            && $this->manageStaff($actor, $target, 'saas.staff.permissions.update');
    }

    public function resendStaffInvitation(User $actor, User $target): bool
    {
        return $target->status === StatusEnum::ACTIVE && ! $target->hasVerifiedEmail()
            && $this->manageStaff($actor, $target, 'saas.staff.invite.resend');
    }

    public function resendInvitation(User $actor, User $target): bool
    {
        return $this->access->isStaff($target)
            ? $this->resendStaffInvitation($actor, $target)
            : $this->update($actor, $target);
    }

    public function renewStaffAssignments(User $actor, User $target): bool
    {
        return ! in_array($target->status, [StatusEnum::PENDING, StatusEnum::REJECTED], true)
            && $this->manageStaff($actor, $target, 'saas.staff.assignments.renew');
    }

    public function suspendStaff(User $actor, User $target): bool
    {
        return $target->status === StatusEnum::ACTIVE && $this->manageStaff($actor, $target, 'saas.staff.suspend');
    }

    public function activateStaff(User $actor, User $target): bool
    {
        return in_array($target->status, [StatusEnum::INACTIVE, StatusEnum::SUSPENDED], true)
            && $this->manageStaff($actor, $target, 'saas.staff.activate');
    }

    public function archiveStaff(User $actor, User $target): bool
    {
        return $this->manageStaff($actor, $target, 'saas.staff.archive');
    }

    private function manageStaff(User $actor, User $target, string $permission): bool
    {
        return $this->access->isStaff($target) && ! $target->trashed() && $actor->id !== $target->id
            && ! $target->roles()->where('guard_name', 'central')->where('is_super_admin', true)->exists()
            && $this->access->allows($actor, $permission) && $this->access->mayManageStaff($actor, $target);
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
        $needsReview = $target->status === StatusEnum::PENDING
            || ($target->status === StatusEnum::ACTIVE && $target->legal_verification_status !== VerificationStatusEnum::VERIFIED);

        return ! $target->trashed() && $actor->id !== $target->id
            && ($needsReview || ($action === 'approve' && $target->status === StatusEnum::REJECTED))
            && ! $this->access->isStaff($target)
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
        return $this->approve($actor, $target);
    }

    public function rejectLegal(User $actor, User $target): bool
    {
        return $this->reject($actor, $target);
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

        return ($this->access->allows($actor, 'saas.users.'.$action)
                || ($legacy !== $action && $this->access->allows($actor, 'saas.users.'.$legacy))
                || ($staff && $this->access->allows($actor, 'saas.staff.'.$action))
                || ($staff && $this->access->allows($actor, 'saas.staff.'.$legacy)))
            && (! $staff || $this->access->mayManageStaff($actor, $target));
    }
}
