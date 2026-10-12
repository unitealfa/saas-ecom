<?php

namespace App\Services\Central\Roles;

use App\DTOs\Central\Roles\RoleData;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RoleManager
{
    public function __construct(private AuthorizationMutation $mutations) {}

    public function save(User $actor, RoleData $data, ?Role $role = null): Role
    {
        return $this->mutations->run(function () use ($actor, $data, $role): Role {
            $role = $role === null ? null : Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($role === null ? 'create' : 'update', $role ?? Role::class);
            $permissions = Permission::where('guard_name', 'central')
                ->whereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS))
                ->whereIn('uuid', array_keys($data->permissionDurations))->orderBy('id')->lockForUpdate()->get();
            if ($permissions->isEmpty() || $permissions->count() !== count($data->permissionDurations)) {
                throw ValidationException::withMessages(['permissions' => 'Sélectionnez des permissions centrales disponibles.']);
            }

            $grants = [];
            $parts = [];
            foreach ($permissions as $permission) {
                $days = $data->permissionDurations[$permission->uuid];
                if ($days < 1 || $days > 9999) {
                    throw ValidationException::withMessages(['durations' => 'Chaque durée doit être comprise entre 1 et 9 999 jours.']);
                }
                $grants[$permission->id] = ['duration_days' => $days];
                $parts[] = [$permission->id, $days];
            }
            $signature = hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
            $duplicates = Role::where('guard_name', 'central')->where(function ($query) use ($data, $signature): void {
                $query->where('name', $data->name)->orWhere('permission_signature', $signature);
            });
            if ($role !== null) {
                $duplicates->whereKeyNot($role->id);
                $this->assertRecipientsRemainDisjoint($role, array_keys($grants));
            }
            if ($duplicates->exists()) {
                throw ValidationException::withMessages(['name' => 'Un rôle possède déjà ce nom ou les mêmes permissions et durées.']);
            }

            $role ??= new Role;
            $oldGrants = $role->exists ? $role->permissions()->get()->mapWithKeys(fn ($permission): array => [
                $permission->getAttribute('uuid') => (int) $permission->getRelation('pivot')->getAttribute('duration_days'),
            ])->all() : [];
            $role->fill(['name' => $data->name, 'label' => $data->label]);
            $role->setAttribute('permission_signature', $signature);
            if ($role->exists) {
                $role->setAttribute('permission_version', $role->getAttribute('permission_version') + 1);
            }
            $role->save();
            $role->permissions()->sync($grants);
            $role->unsetRelation('permissions');
            activity()->causedBy($actor)->performedOn($role)->event('permissions_updated')
                ->withProperties(['before' => $oldGrants, 'after' => $data->permissionDurations])
                ->log('Permissions et durées du rôle central enregistrées.');

            return $role;
        });
    }

    /** @param list<int> $permissionIds */
    private function assertRecipientsRemainDisjoint(Role $role, array $permissionIds): void
    {
        $db = $role->getConnection();
        $recipients = $db->table('model_has_roles')->where('role_id', $role->id)->orderBy('model_id')->get();
        User::whereIn('id', $recipients->where('model_type', 'central_user')->pluck('model_id'))
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($recipients as $recipient) {
            $overlap = $db->table('model_has_roles as assignment')
                ->join('role_has_permissions as grant', 'grant.role_id', '=', 'assignment.role_id')
                ->where('assignment.model_type', $recipient->model_type)->where('assignment.model_id', $recipient->model_id)
                ->where('assignment.role_id', '<>', $role->id)->whereIn('grant.permission_id', $permissionIds)->exists();
            $direct = $db->table('model_has_permissions')->where('model_type', $recipient->model_type)
                ->where('model_id', $recipient->model_id)->whereIn('permission_id', $permissionIds)->exists();
            if ($overlap || $direct) {
                throw ValidationException::withMessages(['permissions' => 'Cette modification ferait partager une permission à plusieurs attributions d’un utilisateur.']);
            }
        }
    }
}
