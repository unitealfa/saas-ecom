<?php

namespace App\Services\Tenant\Roles;

use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAuthorizationMode;
use App\Services\Tenant\Authorization\TenantQuotaService;
use App\Models\Tenant\Shop\Shop;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final class RoleManager
{
    public function __construct(private readonly TenantQuotaService $quotas) {}
    public function state(Role $role): string
    {
        return hash('sha256', json_encode([$role->name, $role->label, $role->permission_signature,
            $role->permission_version, $role->updated_at?->toISOString()], JSON_THROW_ON_ERROR));
    }

    /** @param array{name:string,label:string,permissions:array<string,int>,expected_state?:string} $data */
    public function save(User $actor, ?Role $role, array $data): Role
    {
        abort_unless(TenantAuthorizationMode::individualOnly(), 409);
        try {
            return DB::connection('tenant')->transaction(function () use ($actor, $role, $data): Role {
                Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
                $actor = User::whereKey($actor->id)->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
                $current = $role === null ? null : Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize($current ? 'update' : 'create', $current ?? Role::class);
                if ($current === null) { $this->quotas->assertCanCreateCustomRole(); }
                if ($current !== null && ! hash_equals($this->state($current), $data['expected_state'] ?? '')) {
                    throw ValidationException::withMessages(['expected_state' => 'Profil modifié. Rechargez la page.']);
                }
                $ids = [];
                foreach ($data['permissions'] as $uuid => $days) {
                    $permission = Permission::where('uuid', $uuid)->firstOrFail();
                    abort_unless($actor->can($permission->name), 403);
                    $ids[(int) $permission->id] = (int) $days;
                }
                if ($ids === []) {
                    throw ValidationException::withMessages(['permissions' => 'Choisissez au moins une permission.']);
                }
                ksort($ids);
                $parts = [];
                foreach ($ids as $id => $days) { $parts[] = [$id, $days]; }
                $signature = hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
                $conflict = Role::where('guard_name', Role::GUARD)->where('permission_signature', $signature)
                    ->when($current !== null, fn ($query) => $query->where('id', '<>', $current->id))->exists();
                if ($conflict || Role::where('guard_name', Role::GUARD)->where('name', $data['name'])
                    ->when($current !== null, fn ($query) => $query->where('id', '<>', $current->id))->exists()) {
                    throw ValidationException::withMessages(['name' => 'Nom ou composition déjà utilisé dans cette boutique.']);
                }
                if (in_array($data['name'], ['root', 'super-admin', 'system', 'owner'], true)) {
                    throw ValidationException::withMessages(['name' => 'Nom de profil réservé.']);
                }
                $current ??= new Role;
                $current->forceFill(['name' => $data['name'], 'label' => $data['label'],
                    'guard_name' => Role::GUARD, 'permission_signature' => $signature,
                    'permission_version' => ($current->exists ? (int) $current->permission_version + 1 : 1)])->save();
                $grants = [];
                foreach ($ids as $permissionId => $days) {
                    $grants[$permissionId] = ['duration_days' => $days];
                }
                $current->permissionComposition()->sync($grants);
                $current->unsetRelation('permissions');
                activity()->causedBy($actor)->performedOn($current)->event('tenant_role_saved')
                    ->withProperties(['permission_codes_count' => count($ids)])
                    ->log('Profil local enregistré');
                DB::connection('tenant')->afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
                return $current;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Nom ou composition déjà utilisé.']);
        }
    }

    public function delete(User $actor, Role $role, string $expected): void
    {
        DB::connection('tenant')->transaction(function () use ($actor, $role, $expected): void {
            Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $role);
            if (! hash_equals($this->state($role), $expected)) {
                throw ValidationException::withMessages(['expected_state' => 'Profil modifié. Rechargez.']);
            }
            if (DB::connection('tenant')->table('model_has_roles')->where('role_id', $role->id)->exists()
                || DB::connection('tenant')->table('team_invitations')->where('initial_role_id', $role->id)->exists()) {
                throw ValidationException::withMessages(['role' => 'Profil toujours utilisé ou référencé par une invitation.']);
            }
            $role->permissionComposition()->detach();
            $role->delete();
            activity()->causedBy($actor)->event('tenant_role_deleted')
                ->withProperties(['role_uuid' => $role->uuid])->log('Profil local supprimé');
            DB::connection('tenant')->afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        }, 3);
    }
}
