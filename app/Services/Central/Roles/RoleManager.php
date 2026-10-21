<?php

namespace App\Services\Central\Roles;

use App\DTOs\Central\Roles\RoleData;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RoleManager
{
    public function __construct(private AuthorizationMutation $mutations) {}

    public function save(User $actor, RoleData $data, ?Role $role = null): Role
    {
        return $this->mutations->run(
            actor: $actor,
            ability: $role === null ? 'create' : 'edit',
            loadTarget: fn () => $role === null ? Role::class : Role::whereKey($role->id)->lockForUpdate()->firstOrFail(),
            mutation: function (Role|string $target) use ($actor, $data): Role {
                $role = $target instanceof Role ? $target : null;
                $gate = Gate::forUser($actor);
                if ($role !== null && $data->expectedVersion !== (int) $role->permission_version) {
                    throw ValidationException::withMessages(['expected_version' => 'Ce rôle a changé. Rechargez le formulaire avant de l’enregistrer.']);
                }
                $oldGrants = $role?->permissionComposition()->get()->mapWithKeys(fn ($permission): array => [
                    $permission->uuid => (int) $permission->pivot->duration_days,
                ])->all() ?? [];
                $durations = $data->permissionDurations ?? $oldGrants;
                foreach ($durations as $uuid => $days) {
                    $durations[$uuid] = $days ?? $oldGrants[$uuid] ?? 9999;
                }
                foreach ($data->durations as $uuid => $days) {
                    if (! array_key_exists($uuid, $durations)) {
                        throw ValidationException::withMessages(['durations' => 'Cette permission ne fait pas partie du rôle.']);
                    }
                    $durations[$uuid] = $days;
                }
                $name = $data->name ?? $role?->name;
                $label = $data->label ?? $role?->label;
                if (! is_string($name) || trim($name) === '' || ! is_string($label) || trim($label) === '') {
                    throw ValidationException::withMessages(['name' => 'Renseignez le nom et le libellé du rôle.']);
                }
                $name = trim($name);
                $label = trim($label);
                if ($role !== null) {
                    if ($name !== $role->name || $label !== $role->label) {
                        $gate->authorize('update', $role);
                    }
                    if (array_diff_key($durations, $oldGrants) !== [] || array_diff_key($oldGrants, $durations) !== []) {
                        $gate->authorize('updatePermissions', $role);
                    }
                    foreach ($durations as $uuid => $days) {
                        if ($days !== ($oldGrants[$uuid] ?? 9999)) {
                            $gate->authorize('updateDurations', $role);
                            break;
                        }
                    }
                }
                $permissions = Permission::forCentralGuard()
                    ->whereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS))
                    ->whereIn('uuid', array_keys($durations))->orderBy('id')->lockForUpdate()->get();
                if ($permissions->isEmpty() || $permissions->count() !== count($durations)) {
                    throw ValidationException::withMessages(['permissions' => 'Sélectionnez des permissions centrales disponibles.']);
                }

                $grants = [];
                $parts = [];
                foreach ($permissions as $permission) {
                    $gate->authorize('delegate', $permission);
                    $days = $durations[$permission->uuid];
                    if ($days < 1 || $days > 9999) {
                        throw ValidationException::withMessages(['durations' => 'Chaque durée doit être comprise entre 1 et 9 999 jours.']);
                    }
                    $grants[$permission->id] = ['duration_days' => $days];
                    $parts[] = [$permission->id, $days];
                }
                $signature = hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
                $duplicates = Role::forCentralGuard()->where(function ($query) use ($name, $signature): void {
                    $query->where('name', $name)->orWhere('permission_signature', $signature);
                });
                if ($role !== null) {
                    $duplicates->whereKeyNot($role->id);
                    if (! $role->usesIndividualPermissions()) {
                        $this->assertRecipientsRemainDisjoint($role, array_keys($grants));
                    }
                }
                if ($duplicates->exists()) {
                    throw ValidationException::withMessages(['name' => 'Un rôle possède déjà ce nom ou les mêmes permissions et durées.']);
                }

                $created = $role === null;
                $role ??= new Role;
                $before = ['name' => $role->name, 'label' => $role->label, 'permissions' => $oldGrants];
                $permissionsChanged = $role->permission_signature !== $signature;
                $role->fill(['name' => $name, 'label' => $label]);
                $role->setAttribute('permission_signature', $signature);
                if (! $created && ! $role->isDirty()) {
                    return $role;
                }
                if (! $created) {
                    $role->setAttribute('permission_version', $role->getAttribute('permission_version') + 1);
                }
                $role->save();
                if ($permissionsChanged) {
                    $role->permissionComposition()->sync($grants);
                }
                $role->unsetRelation('permissions');
                activity()->causedBy($actor)->performedOn($role)->event($created ? 'role_created' : 'role_updated')
                    ->withProperties(['before' => $before, 'after' => ['name' => $name, 'label' => $label, 'permissions' => $durations]])
                    ->log('Rôle central enregistré.');

                return $role;
            },
            refreshPermissionCache: true,
        );
    }

    public function delete(User $actor, Role $role): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: 'delete',
            loadTarget: fn () => Role::whereKey($role->id)->lockForUpdate()->firstOrFail(),
            mutation: function (Role $role) use ($actor): void {
                $connection = $role->getConnection();
                if ($connection->table(config('permission.table_names.model_has_roles'))->where('role_id', $role->id)->exists()) {
                    throw ValidationException::withMessages(['role' => 'Ce rôle est encore attribué. Retirez ses attributions avant de le supprimer.']);
                }
                if ($connection->getSchemaBuilder()->hasTable('admin_restrictions')
                    && $connection->table('admin_restrictions')->where('target_role_id', $role->id)->exists()) {
                    throw ValidationException::withMessages(['role' => 'Une référence métier existante empêche la suppression de ce rôle.']);
                }
                $before = ['uuid' => $role->uuid, 'name' => $role->name, 'label' => $role->label,
                    'permissions' => $role->permissionComposition()->get()->mapWithKeys(fn ($permission): array => [
                        $permission->uuid => (int) $permission->pivot->duration_days,
                    ])->all()];
                try {
                    $role->permissions()->detach();
                    $role->delete();
                } catch (QueryException $exception) {
                    if (($exception->errorInfo[1] ?? null) === 1451) {
                        throw ValidationException::withMessages(['role' => 'Une référence métier existante empêche la suppression de ce rôle.']);
                    }
                    throw $exception;
                }
                activity()->causedBy($actor)->performedOn($role)->event('role_deleted')
                    ->withProperties(['before' => $before])->log('Rôle central personnalisé inutilisé supprimé.');
            },
            refreshPermissionCache: true,
        );
    }

    /** @param list<int> $permissionIds */
    private function assertRecipientsRemainDisjoint(Role $role, array $permissionIds): void
    {
        $db = $role->getConnection();
        $recipients = $db->table('model_has_roles')->where('role_id', $role->id)->orderBy('model_id')->get();
        User::withTrashed()->whereIn('id', $recipients->where('model_type', 'central_user')->pluck('model_id'))
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
