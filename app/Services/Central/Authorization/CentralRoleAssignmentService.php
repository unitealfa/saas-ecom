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
        $this->users->assignRoles($actor, $target, $roleUuids);
    }

    public function renewRole(User $actor, User $target, Role $role, string $reason, string $expectedAssignedAt): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: 'renewStaffAssignments',
            loadTarget: fn () => User::whereKey($target->id)->lockForUpdate()->firstOrFail(),
            mutation: function (User $target) use ($actor, $role, $reason, $expectedAssignedAt): void {
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
                $renewed = [];
                if ($role->usesIndividualPermissions()) {
                    foreach ($role->permissionComposition()->get() as $permission) {
                        $direct = $target->permissions()->whereKey($permission->id)->first();
                        if ($direct === null) {
                            continue;
                        }
                        $renewed[] = ['permission_uuid' => $permission->uuid,
                            'previous_assigned_at' => $direct->pivot->assigned_at->toIso8601String(),
                            'previous_expires_at' => $direct->pivot->expires_at->toIso8601String(),
                            'assigned_at' => $assignedAt->toIso8601String(),
                            'expires_at' => $assignedAt->copy()->addDays((int) $permission->pivot->duration_days)->toIso8601String()];
                        $target->permissions()->updateExistingPivot($permission->id, [
                            'assigned_at' => $assignedAt,
                            'expires_at' => $assignedAt->copy()->addDays((int) $permission->pivot->duration_days),
                        ]);
                    }
                }
                $target->unsetRelation('roles');
                activity()->causedBy($actor)->performedOn($target)->event('central_role_assignment_renewed')
                    ->withProperties([
                        'role_uuid' => $role->uuid, 'previous_assigned_at' => $before->toIso8601String(),
                        'assigned_at' => $assignedAt->toIso8601String(), 'reason' => trim($reason),
                        'renewed_direct_permissions' => $renewed,
                    ])->log('Attribution du rôle SaaS renouvelée explicitement.');
            },
        );
    }
}
