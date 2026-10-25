<?php

namespace App\Services\Central\Roles;

use App\DTOs\Central\Roles\RoleData;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\Central\User;
use App\Models\PermissionAssignment;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type Durations array<string, int>
 * @phpstan-type Grants array<int, array{duration_days: int}>
 */
class RoleManager
{
    private const int NEVER = PermissionAssignment::NEVER_EXPIRES_DAYS;

    private const int MIN_DAYS = 1;

    /** Code d'erreur MySQL/MariaDB : violation de clé étrangère à la suppression. */
    private const int FOREIGN_KEY_ERROR = 1451;

    private const string EVENT_CREATED = 'role_created';

    private const string EVENT_UPDATED = 'role_updated';

    private const string EVENT_DELETED = 'role_deleted';

    private const string DUPLICATE_MESSAGE = 'Un rôle possède déjà ce nom ou les mêmes permissions et durées.';

    private const string REFERENCE_MESSAGE = 'Une référence métier existante empêche la suppression de ce rôle.';

    public function __construct(private AuthorizationMutation $mutations) {}

    public function save(User $actor, RoleData $data, ?Role $role = null): Role
    {
        return $this->mutations->run(
            actor: $actor,
            ability: $role === null ? 'create' : 'edit',
            loadTarget: fn () => $role === null ? Role::class : $this->lock($role),
            mutation: fn (Role|string $target): Role => $this->persist($actor, $data, $target instanceof Role ? $target : null),
        );
    }

    public function delete(User $actor, Role $role): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: 'delete',
            loadTarget: fn () => $this->lock($role),
            mutation: function (Role $role) use ($actor): void {
                $this->assertDeletable($role);

                $before = [
                    'uuid' => $role->uuid,
                    'name' => $role->name,
                    'label' => $role->label,
                    'permissions' => $this->grantsOf($role),
                ];

                try {
                    $role->permissions()->detach();
                    $role->delete();
                } catch (QueryException $exception) {
                    if (($exception->errorInfo[1] ?? null) === self::FOREIGN_KEY_ERROR) {
                        throw ValidationException::withMessages(['role' => self::REFERENCE_MESSAGE]);
                    }

                    throw $exception;
                }

                activity()->causedBy($actor)->performedOn($role)->event(self::EVENT_DELETED)
                    ->withProperties(['before' => $before])->log('Rôle central personnalisé inutilisé supprimé.');

                DB::afterCommit(fn () => $role->forgetCachedPermissions());
            },
        );
    }

    private function persist(User $actor, RoleData $data, ?Role $role): Role
    {
        $gate = Gate::forUser($actor);

        if ($role !== null && $data->expectedVersion !== (int) $role->permission_version) {
            throw ValidationException::withMessages(['expected_version' => 'Ce rôle a changé. Rechargez le formulaire avant de l’enregistrer.']);
        }

        $oldGrants = $role === null ? [] : $this->grantsOf($role);
        $durations = $this->resolveDurations($data, $oldGrants);

        [$name, $label] = $this->resolveIdentity($data, $role);

        if ($role !== null) {
            $this->authorizeChanges($gate, $role, $name, $label, $oldGrants, $durations);
        }

        [$grants, $signature] = $this->resolveGrants($gate, $durations);

        $this->assertNoDuplicate($role, $name, $signature, array_keys($grants));

        return $this->store($actor, $role, $name, $label, $signature, $grants, $oldGrants, $durations);
    }

    /**
     * @param  Durations  $oldGrants
     * @return Durations
     */
    private function resolveDurations(RoleData $data, array $oldGrants): array
    {
        $durations = $data->permissionDurations ?? $oldGrants;

        foreach ($durations as $uuid => $days) {
            $durations[$uuid] = $days ?? $oldGrants[$uuid] ?? self::NEVER;
        }

        foreach ($data->durations as $uuid => $days) {
            if (! array_key_exists($uuid, $durations)) {
                throw ValidationException::withMessages(['durations' => 'Cette permission ne fait pas partie du rôle.']);
            }

            $durations[$uuid] = $days;
        }

        return $durations;
    }

    /** @return array{string, string} */
    private function resolveIdentity(RoleData $data, ?Role $role): array
    {
        $name = $data->name ?? $role?->name;
        $label = $data->label ?? $role?->label;

        if (! is_string($name) || trim($name) === '' || ! is_string($label) || trim($label) === '') {
            throw ValidationException::withMessages(['name' => 'Renseignez le nom et le libellé du rôle.']);
        }

        return [trim($name), trim($label)];
    }

    /**
     * @param  Durations  $oldGrants
     * @param  Durations  $durations
     */
    private function authorizeChanges(GateContract $gate, Role $role, string $name, string $label, array $oldGrants, array $durations): void
    {
        if ($name !== $role->name || $label !== $role->label) {
            $gate->authorize('update', $role);
        }

        if (array_diff_key($durations, $oldGrants) !== [] || array_diff_key($oldGrants, $durations) !== []) {
            $gate->authorize('updatePermissions', $role);
        }

        foreach ($durations as $uuid => $days) {
            if ($days !== ($oldGrants[$uuid] ?? self::NEVER)) {
                $gate->authorize('updateDurations', $role);

                break;
            }
        }
    }

    /**
     * @param  Durations  $durations
     * @return array{Grants, string}
     */
    private function resolveGrants(GateContract $gate, array $durations): array
    {
        $permissions = Permission::forCentralGuard()
            ->whereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS))
            ->whereIn('uuid', array_keys($durations))
            ->orderBy('id')->lockForUpdate()->get();

        if ($permissions->isEmpty() || $permissions->count() !== count($durations)) {
            throw ValidationException::withMessages(['permissions' => 'Sélectionnez des permissions centrales disponibles.']);
        }

        $grants = [];
        $parts = [];

        foreach ($permissions as $permission) {
            $gate->authorize('delegate', $permission);

            $days = $durations[$permission->uuid];

            if ($days < self::MIN_DAYS || $days > self::NEVER) {
                throw ValidationException::withMessages(['durations' => 'Chaque durée doit être comprise entre '.self::MIN_DAYS.' et '.number_format(self::NEVER, 0, ',', ' ').' jours.']);
            }

            $grants[$permission->id] = ['duration_days' => $days];
            $parts[] = [$permission->id, $days];
        }

        return [$grants, hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR))];
    }

    /** @param list<int> $permissionIds */
    private function assertNoDuplicate(?Role $role, string $name, string $signature, array $permissionIds): void
    {
        $duplicates = Role::forCentralGuard()->where(function ($query) use ($name, $signature): void {
            $query->where('name', $name)->orWhere('permission_signature', $signature);
        });

        if ($role !== null) {
            $duplicates->whereKeyNot($role->id);

            if (! $role->usesIndividualPermissions()) {
                $this->assertRecipientsRemainDisjoint($role, $permissionIds);
            }
        }

        if ($duplicates->exists()) {
            throw ValidationException::withMessages(['name' => self::DUPLICATE_MESSAGE]);
        }
    }

    /**
     * @param  Grants  $grants
     * @param  Durations  $oldGrants
     * @param  Durations  $durations
     */
    private function store(User $actor, ?Role $role, string $name, string $label, string $signature, array $grants, array $oldGrants, array $durations): Role
    {
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

        try {
            $role->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => self::DUPLICATE_MESSAGE]);
        }

        if ($permissionsChanged) {
            $role->permissionComposition()->sync($grants);
        }

        $role->unsetRelation('permissions');

        activity()->causedBy($actor)->performedOn($role)
            ->event($created ? self::EVENT_CREATED : self::EVENT_UPDATED)
            ->withProperties(['before' => $before, 'after' => ['name' => $name, 'label' => $label, 'permissions' => $durations]])
            ->log('Rôle central enregistré.');

        DB::afterCommit(fn () => $role->forgetCachedPermissions());

        return $role;
    }

    private function assertDeletable(Role $role): void
    {
        $connection = $role->getConnection();

        if ($connection->table(config('permission.table_names.model_has_roles'))->where('role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['role' => 'Ce rôle est encore attribué. Retirez ses attributions avant de le supprimer.']);
        }

        if ($connection->getSchemaBuilder()->hasTable('admin_restrictions')
            && $connection->table('admin_restrictions')->where('target_role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['role' => self::REFERENCE_MESSAGE]);
        }
    }

    /** @return Durations */
    private function grantsOf(Role $role): array
    {
        $permissions = $role->permissionComposition()->get();

        return $permissions->mapWithKeys(fn ($permission): array => [
            $permission->uuid => (int) $permission->getRelation('pivot')->getAttribute('duration_days'),
        ])->all();
    }

    private function lock(Role $role): Role
    {
        return Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
    }

    /** @param list<int> $permissionIds */
    private function assertRecipientsRemainDisjoint(Role $role, array $permissionIds): void
    {
        $db = $role->getConnection();
        $roleTable = config('permission.table_names.model_has_roles');
        $permissionTable = config('permission.table_names.model_has_permissions');
        $grantTable = config('permission.table_names.role_has_permissions');

        $recipients = $db->table($roleTable)->where('role_id', $role->id)->orderBy('model_id')->get();

        User::withTrashed()
            ->whereIn('id', $recipients->where('model_type', (new User)->getMorphClass())->pluck('model_id'))
            ->orderBy('id')->lockForUpdate()->get();

        foreach ($recipients as $recipient) {
            $overlap = $db->table($roleTable.' as assignment')
                ->join($grantTable.' as grant', 'grant.role_id', '=', 'assignment.role_id')
                ->where('assignment.model_type', $recipient->model_type)
                ->where('assignment.model_id', $recipient->model_id)
                ->where('assignment.role_id', '<>', $role->id)
                ->whereIn('grant.permission_id', $permissionIds)
                ->exists();

            $direct = $db->table($permissionTable)
                ->where('model_type', $recipient->model_type)
                ->where('model_id', $recipient->model_id)
                ->whereIn('permission_id', $permissionIds)
                ->exists();

            if ($overlap || $direct) {
                throw ValidationException::withMessages(['permissions' => 'Cette modification ferait partager une permission à plusieurs attributions d’un utilisateur.']);
            }
        }
    }
}
