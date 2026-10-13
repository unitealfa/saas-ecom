<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\Users\UserManagementService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CentralRoleAssignmentService
{
    public function __construct(
        private AuthorizationMutation $mutations,
        private UserManagementService $users,
        private CentralUserAccess $access,
    ) {}

    /** @param list<string> $roleUuids */
    public function replace(User $actor, User $target, array $roleUuids): void
    {
        $this->mutations->run(function () use ($actor, $target, $roleUuids): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('assignStaffRoles', $target);
            $before = $target->roles()->get()->map(fn ($role): array => [
                'role_uuid' => $role->uuid, 'assigned_at' => $role->pivot->assigned_at->toIso8601String(),
            ])->all();
            $this->users->assignRoles($actor, $target, $roleUuids);
            $after = $target->roles()->get()->map(fn ($role): array => [
                'role_uuid' => $role->uuid, 'assigned_at' => $role->pivot->assigned_at->toIso8601String(),
            ])->all();
            if ($before !== $after) {
                activity()->causedBy($actor)->performedOn($target)->event('central_staff_roles_replaced')
                    ->withProperties(['before' => $before, 'after' => $after])
                    ->log('Attributions des rôles du personnel SaaS modifiées.');
            }
        });
    }

    public function renewRole(User $actor, User $target, Role $role, string $reason, string $expectedAssignedAt): void
    {
        $this->mutations->run(function () use ($actor, $target, $role, $reason, $expectedAssignedAt): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('renewStaffAssignments', $target);
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->ownsRolePermissions($actor, $role), 403);
            $assigned = $target->roles()->whereKey($role->id)->first();
            if ($assigned === null) {
                throw ValidationException::withMessages(['role' => 'Ce rôle n’est plus attribué à ce compte.']);
            }
            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Indiquez le motif du renouvellement.']);
            }
            $before = $assigned->pivot->assigned_at;
            if (! $before->equalTo(CarbonImmutable::parse($expectedAssignedAt))) {
                throw ValidationException::withMessages(['role' => 'Cette attribution a changé. Rechargez la fiche avant de renouveler.']);
            }
            $assignedAt = now()->startOfSecond();
            if ($before->equalTo($assignedAt)) {
                return;
            }
            $target->roles()->updateExistingPivot($role->id, ['assigned_at' => $assignedAt]);
            $target->unsetRelation('roles');
            activity()->causedBy($actor)->performedOn($target)->event('central_role_assignment_renewed')
                ->withProperties([
                    'role_uuid' => $role->uuid, 'previous_assigned_at' => $before->toIso8601String(),
                    'assigned_at' => $assignedAt->toIso8601String(), 'reason' => trim($reason),
                ])->log('Attribution du rôle SaaS renouvelée explicitement.');
        });
    }
}
