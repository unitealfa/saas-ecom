<?php

namespace App\Services\Tenant\Users;

use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Users\User;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant\Shop\Shop;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UserRoleService
{
    public function state(User $member): string
    {
        $roles = $member->roles()->orderBy('roles.id')->get()->map(fn ($role): array => [
            $role->uuid, $role->pivot->assigned_at?->toDateTimeString(),
        ])->all();
        return hash('sha256', json_encode($roles, JSON_THROW_ON_ERROR));
    }

    public function change(User $actor, User $member, Role $role, string $action, string $expected): void
    {
        DB::connection('tenant')->transaction(function () use ($actor, $member, $role, $action, $expected): void {
            Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            $member = User::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageRoles', $member);
            Gate::forUser($actor)->authorize('assign', $role);
            if (! hash_equals($this->state($member), $expected)) {
                throw ValidationException::withMessages(['expected_state' => 'Attributions modifiées. Rechargez.']);
            }
            if ($action === 'add') {
                foreach ($role->permissionComposition()->get() as $permission) {
                    abort_unless($actor->can($permission->name), 403);
                }
                $member->assignRole($role); // creates ONLY missing direct grants for a NEW role assignment.
            } elseif ($action === 'remove') {
                $member->removeRole($role); // individual direct grants deliberately remain unchanged.
            } else { abort(422); }
            activity()->causedBy($actor)->performedOn($member)->event('tenant_member_role_'.$action)
                ->withProperties(['role_uuid' => $role->uuid])->log('Profil local attribué ou retiré');
        }, 3);
    }
}
