<?php

namespace App\Services\Tenant\Users;

use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Users\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Shop\Shop;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UserPermissionService
{
    public function state(User $member): string
    {
        $grants = $member->permissions()->orderBy('permissions.id')->get()->map(fn ($permission): array => [
            $permission->uuid, $permission->pivot->assigned_at?->toDateTimeString(),
            $permission->pivot->expires_at?->toDateTimeString(),
        ])->all();
        return hash('sha256', json_encode($grants, JSON_THROW_ON_ERROR));
    }

    public function change(User $actor, User $member, Permission $permission, string $action, int $days, string $expected, string $reason): void
    {
        abort_unless(tenancy()->initialized, 403);
        DB::connection('tenant')->transaction(function () use ($actor, $member, $permission, $action, $days, $expected, $reason): void {
            Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            $member = User::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $permission = Permission::whereKey($permission->id)->firstOrFail();
            Gate::forUser($actor)->authorize($action === 'renew' ? 'renewPermission' : 'managePermissions', $member);
            abort_unless($actor->can($permission->name), 403);
            if (! hash_equals($this->state($member), $expected)) {
                throw ValidationException::withMessages(['expected_state' => 'Droits modifiés. Rechargez.']);
            }
            $exists = $member->permissions()->whereKey($permission->id)->exists();
            if ($action === 'revoke') {
                abort_unless($exists, 409);
                $member->revokePermissionTo($permission);
            } elseif ($action === 'grant') {
                if ($exists) {
                    throw ValidationException::withMessages(['permission' => 'Ce droit existe déjà. Utilisez renouveler.']);
                }
                $member->givePermissionTo($permission);
                $assignedAt = CarbonImmutable::now();
                $member->permissions()->updateExistingPivot($permission->id, [
                    'assigned_at' => $assignedAt, 'expires_at' => $assignedAt->addDays($days),
                ]);
            } elseif ($action === 'renew') {
                abort_unless($exists, 409);
                $assignedAt = CarbonImmutable::now();
                $member->permissions()->updateExistingPivot($permission->id, [
                    'assigned_at' => $assignedAt, 'expires_at' => $assignedAt->addDays($days),
                ]);
            } else { abort(422); }
            $member->unsetRelation('permissions');
            activity()->causedBy($actor)->performedOn($member)->event('tenant_member_permission_'.$action)
                ->withProperties(['permission' => $permission->name, 'days' => $action !== 'revoke' ? $days : null,
                    'reason' => $reason])->log('Droit individuel modifié');
        }, 3);
    }
}
