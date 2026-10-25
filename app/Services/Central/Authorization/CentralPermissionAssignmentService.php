<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Permission;
use App\Models\Central\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type Grant array{permission: string, enabled: bool|int|string, days: int|string, renew?: bool|int|string}
 * @phpstan-type Snapshot array<int, array{permission_uuid: string, assigned_at: string, expires_at: string}>
 */
class CentralPermissionAssignmentService
{
    private const string ABILITY = 'updateStaffPermissions';

    private const string EVENT_UPDATED = 'central_staff_permissions_updated';

    private const int MIN_DAYS = 1;

    private const int MAX_DAYS = 9999;

    public function __construct(private AuthorizationMutation $mutations) {}

    public function version(User $target): string
    {
        return $this->stateHash($target, $this->snapshot($target));
    }

    /** @param list<Grant> $grants */
    public function update(User $actor, User $target, array $grants, string $expectedState, ?string $reason): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: self::ABILITY,
            loadTarget: fn () => User::whereKey($target->id)->lockForUpdate()->firstOrFail(),
            mutation: function (User $target) use ($actor, $grants, $expectedState, $reason): void {
                $before = $this->snapshot($target);

                if (! hash_equals($this->stateHash($target, $before), $expectedState)) {
                    throw ValidationException::withMessages(['expected_state' => 'Les attributions ont changé. Rechargez la page.']);
                }

                $changes = $this->plan($actor, $target, $grants, $reason);

                foreach ($changes as $change) {
                    $this->apply($target, $change['permission'], $change['days']);
                }

                $after = $this->snapshot($target);

                if ($before !== $after) {
                    activity()->causedBy($actor)->performedOn($target)->event(self::EVENT_UPDATED)
                        ->withProperties(['before' => $before, 'after' => $after, 'reason' => $reason])
                        ->log('Permissions individuelles du personnel modifiées via Spatie.');
                }
            },
        );
    }

    /**
     * @param  list<Grant>  $grants
     * @return list<array{permission: Permission, days: int|null}>
     */
    private function plan(User $actor, User $target, array $grants, ?string $reason): array
    {
        $permissions = Permission::forCentralGuard()
            ->whereIn('uuid', array_column($grants, 'permission'))
            ->get()->keyBy('uuid');
        $current = $target->permissions()->get()->keyBy('id');

        $seen = [];
        $changes = [];

        foreach ($grants as $grant) {
            $permission = $permissions->get($grant['permission']) ?? abort(404);

            Gate::forUser($actor)->authorize('delegate', $permission);

            if (isset($seen[$permission->id])) {
                throw ValidationException::withMessages(['grants' => 'Une permission ne peut apparaître qu’une fois.']);
            }
            $seen[$permission->id] = true;

            $existing = $current->get($permission->id);

            if (! filter_var($grant['enabled'], FILTER_VALIDATE_BOOLEAN)) {
                if ($existing !== null) {
                    $changes[] = ['permission' => $permission, 'days' => null];
                }

                continue;
            }

            $days = (int) $grant['days'];

            if ($days < self::MIN_DAYS || $days > self::MAX_DAYS) {
                throw ValidationException::withMessages(['grants' => 'La durée doit être comprise entre 1 et 9 999 jours.']);
            }

            $renew = filter_var($grant['renew'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($existing !== null && ! $renew) {
                if ($days !== $this->grantedDays($existing)) {
                    throw ValidationException::withMessages(['grants' => 'Pour changer une durée, demandez explicitement un renouvellement motivé.']);
                }

                continue;
            }

            if ($existing !== null) {
                Gate::forUser($actor)->authorize('renewStaffAssignments', $target);

                if (trim($reason ?? '') === '') {
                    throw ValidationException::withMessages(['reason' => 'Le motif du renouvellement est obligatoire.']);
                }
            }

            $changes[] = ['permission' => $permission, 'days' => $days];
        }

        return $changes;
    }

    private function apply(User $target, Permission $permission, ?int $days): void
    {
        if ($days === null) {
            $target->revokePermissionTo($permission);

            return;
        }

        $start = now()->startOfSecond();

        $target->givePermissionTo($permission);
        $target->permissions()->updateExistingPivot($permission->id, [
            'assigned_at' => $start,
            'expires_at' => $start->copy()->addDays($days),
        ]);
    }

    private function grantedDays(Permission $permission): int
    {
        $pivot = $permission->getRelation('pivot');

        return (int) $pivot->getAttribute('assigned_at')->diffInDays($pivot->getAttribute('expires_at'));
    }

    /** @param Snapshot $snapshot */
    private function stateHash(User $target, array $snapshot): string
    {
        return hash('sha256', json_encode([
            $target->getRawOriginal('status'),
            $target->getRawOriginal('deleted_at'),
            $target->roles()->orderBy('roles.id')->get()->map(fn ($role) => [
                $role->uuid, $role->permission_version, $role->pivot->assigned_at->toIso8601String(),
            ])->all(),
            $snapshot,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return Snapshot */
    private function snapshot(User $target): array
    {
        return $target->permissions()->orderBy('permissions.id')->get()->map(fn ($permission) => [
            'permission_uuid' => $permission->uuid,
            'assigned_at' => (string) $permission->getRelation('pivot')->getAttribute('assigned_at')->toIso8601String(),
            'expires_at' => (string) $permission->getRelation('pivot')->getAttribute('expires_at')->toIso8601String(),
        ])->values()->all();
    }
}
