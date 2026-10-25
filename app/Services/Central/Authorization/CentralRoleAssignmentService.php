<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\Users\UserManagementService;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Validation\ValidationException;

class CentralRoleAssignmentService
{
    private const string ABILITY = 'renewStaffAssignments';

    private const string EVENT_RENEWED = 'central_role_assignment_renewed';

    public function __construct(
        private AuthorizationMutation $mutations,
        private UserManagementService $users,
        private CentralUserAccess $access,
    ) {}

    public function replace(User $actor, User $target, array $roleUuids): void
    {
        $this->users->assignRoles($actor, $target, $roleUuids);
    }

    public function renewRole(User $actor, User $target, Role $role, string $reason, string $expectedAssignedAt): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: self::ABILITY,
            loadTarget: fn () => User::whereKey($target->id)->lockForUpdate()->firstOrFail(),
            mutation: function (User $target) use ($actor, $role, $reason, $expectedAssignedAt): void {
                $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();

                abort_unless($this->access->ownsRolePermissions($actor, $role), 403);

                $assigned = $target->roles()->whereKey($role->id)->first();

                if ($assigned === null) {
                    throw ValidationException::withMessages(['role' => 'Ce rôle n’est plus attribué à ce compte.']);
                }

                $reason = trim($reason);

                if ($reason === '') {
                    throw ValidationException::withMessages(['reason' => 'Indiquez le motif du renouvellement.']);
                }

                $before = $assigned->pivot->assigned_at;

                if (! $before->equalTo($this->parseDate($expectedAssignedAt))) {
                    throw ValidationException::withMessages(['role' => 'Cette attribution a changé. Rechargez la fiche avant de renouveler.']);
                }

                $assignedAt = now()->startOfSecond();

                if ($before->equalTo($assignedAt)) {
                    return;
                }

                $target->roles()->updateExistingPivot($role->id, ['assigned_at' => $assignedAt]);

                $renewed = $role->usesIndividualPermissions()
                    ? $this->renewDirectPermissions($target, $role, $assignedAt)
                    : [];

                $target->unsetRelation('roles')->unsetRelation('permissions');

                activity()->causedBy($actor)->performedOn($target)->event(self::EVENT_RENEWED)
                    ->withProperties([
                        'role_uuid' => $role->uuid,
                        'previous_assigned_at' => $before->toIso8601String(),
                        'assigned_at' => $assignedAt->toIso8601String(),
                        'reason' => $reason,
                        'renewed_direct_permissions' => $renewed,
                    ])->log('Attribution du rôle SaaS renouvelée explicitement.');
            },
        );
    }

    private function parseDate(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (InvalidFormatException) {
            throw ValidationException::withMessages(['role' => 'La date de l’attribution est invalide.']);
        }
    }

    private function renewDirectPermissions(User $target, Role $role, CarbonImmutable $assignedAt): array
    {
        $direct = $target->permissions()->get()->keyBy('id');
        $renewed = [];

        foreach ($role->permissionComposition()->get() as $permission) {
            $current = $direct->get($permission->id);

            if ($current === null) {
                continue;
            }

            $expiresAt = $assignedAt->copy()->addDays((int) $permission->pivot->duration_days);

            $renewed[] = [
                'permission_uuid' => $permission->uuid,
                'previous_assigned_at' => $current->pivot->assigned_at->toIso8601String(),
                'previous_expires_at' => $current->pivot->expires_at->toIso8601String(),
                'assigned_at' => $assignedAt->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
            ];

            $target->permissions()->updateExistingPivot($permission->id, [
                'assigned_at' => $assignedAt,
                'expires_at' => $expiresAt,
            ]);
        }

        return $renewed;
    }
}