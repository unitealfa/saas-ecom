<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Permission;
use App\Models\Central\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CentralPermissionAssignmentService
{
    public function __construct(private AuthorizationMutation $mutations) {}

    public function version(User $target): string
    {
        return hash('sha256', json_encode([
            $target->getRawOriginal('status'), $target->getRawOriginal('deleted_at'),
            $target->roles()->orderBy('roles.id')->get()->map(fn ($role) => [
                $role->uuid, $role->permission_version, $role->pivot->assigned_at->toIso8601String(),
            ])->all(),
            $this->snapshot($target),
        ], JSON_THROW_ON_ERROR));
    }

    /** @param list<array{permission: string, enabled: bool|int|string, days: int|string, renew?: bool|int|string}> $grants */
    public function update(User $actor, User $target, array $grants, string $expectedState, ?string $reason): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: 'updateStaffPermissions',
            loadTarget: fn () => User::whereKey($target->id)->lockForUpdate()->firstOrFail(),
            mutation: function (User $target) use ($actor, $grants, $expectedState, $reason): void {
                if (! hash_equals($this->version($target), $expectedState)) {
                    throw ValidationException::withMessages(['expected_state' => 'Les attributions ont changé. Rechargez la page.']);
                }
                $before = $this->snapshot($target);
                $seen = [];
                foreach ($grants as $grant) {
                    $permission = Permission::forCentralGuard()->where('uuid', $grant['permission'])->firstOrFail();
                    Gate::forUser($actor)->authorize('delegate', $permission);
                    if (isset($seen[$permission->id])) {
                        throw ValidationException::withMessages(['grants' => 'Une permission ne peut apparaître qu’une fois.']);
                    }
                    $seen[$permission->id] = true;
                    $existing = $target->permissions()->whereKey($permission->id)->first();
                    if (! filter_var($grant['enabled'], FILTER_VALIDATE_BOOLEAN)) {
                        if ($existing !== null) {
                            $target->revokePermissionTo($permission);
                        }
                        continue;
                    }
                    $days = (int) $grant['days'];
                    if ($days < 1 || $days > 9999) {
                        throw ValidationException::withMessages(['grants' => 'La durée doit être comprise entre 1 et 9 999 jours.']);
                    }
                    $renew = filter_var($grant['renew'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    if ($existing !== null && ! $renew) {
                        $oldDays = (int) $existing->pivot->assigned_at->diffInDays($existing->pivot->expires_at);
                        if ($days !== $oldDays) {
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
                    $start = now()->startOfSecond();
                    $target->givePermissionTo($permission);
                    $target->permissions()->updateExistingPivot($permission->id, [
                        'assigned_at' => $start, 'expires_at' => $start->copy()->addDays($days),
                    ]);
                }
                $after = $this->snapshot($target);
                if ($before !== $after) {
                    activity()->causedBy($actor)->performedOn($target)->event('central_staff_permissions_updated')
                        ->withProperties(['before' => $before, 'after' => $after, 'reason' => $reason])
                        ->log('Permissions individuelles du personnel modifiées via Spatie.');
                }
            },
        );
    }

    private function snapshot(User $target): array
    {
        return $target->permissions()->orderBy('permissions.id')->get()->map(fn ($permission) => [
            'permission_uuid' => $permission->uuid,
            'assigned_at' => $permission->pivot->assigned_at->toIso8601String(),
            'expires_at' => $permission->pivot->expires_at->toIso8601String(),
        ])->all();
    }
}
