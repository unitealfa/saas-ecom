<?php

namespace App\Services\Tenant\Authorization;

use App\Models\Tenant\Users\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

/** Convert historical inherited grants without renewing them. No automatic run on db:seed. */
final class AuthorizationUpgradeService
{
    /** @return array{ready:bool,already_converted:bool,grants:int,conflicts:list<string>} */
    public function diagnose(): array
    {
        abort_unless(tenancy()->initialized && Schema::connection('tenant')->hasTable('tenant_authorization_state'), 403);
        $db = DB::connection('tenant');
        if (TenantAuthorizationMode::individualOnly()) {
            return ['ready' => true, 'already_converted' => true, 'grants' => 0, 'conflicts' => []];
        }
        $grants = $this->inheritances();
        $conflicts = [];
        $new = [];
        $direct = $db->table('model_has_permissions')->where('model_type', 'shop_user')->get()
            ->keyBy(fn ($item): string => $item->model_id.':'.$item->permission_id);
        foreach ($grants as $key => $periods) {
            $distinct = array_unique(array_map(fn (array $p): string => implode('|', $p), $periods));
            if (count($distinct) > 1) {
                $conflicts[] = 'Attributions héritées contradictoires pour '.$key;
                continue;
            }
            $old = $direct->get($key);
            $candidate = $periods[0];
            if ($old !== null) {
                // An existing direct grant may be left alone only if it covers the inherited interval.
                if (CarbonImmutable::parse($old->assigned_at)->greaterThan(CarbonImmutable::parse($candidate[0]))
                    || CarbonImmutable::parse($old->expires_at)->lessThan(CarbonImmutable::parse($candidate[1]))) {
                    $conflicts[] = 'Droit direct et héritage contradictoires pour '.$key;
                }
            } else {
                $new[$key] = true;
            }
        }
        return ['ready' => $conflicts === [], 'already_converted' => false,
            'grants' => count($new), 'conflicts' => $conflicts];
    }

    public function apply(): array
    {
        abort_unless(tenancy()->initialized, 403);
        return DB::connection('tenant')->transaction(function (): array {
            $db = DB::connection('tenant');
            $state = $db->table('tenant_authorization_state')->where('id', 1)->lockForUpdate()->first();
            if ($state === null) {
                throw new LogicException('Missing tenant authorization state.');
            }
            if ($state->individual_only) {
                return $this->diagnose();
            }
            // Lock users before calculating grants; do not silently resolve conflicts.
            $db->table('users')->orderBy('id')->lockForUpdate()->get(['id']);
            $diagnostic = $this->diagnose();
            if (! $diagnostic['ready']) {
                return $diagnostic;
            }
            $direct = $db->table('model_has_permissions')->where('model_type', 'shop_user')->get()
                ->keyBy(fn ($item): string => $item->model_id.':'.$item->permission_id);
            foreach ($this->inheritances() as $key => $periods) {
                if ($direct->has($key)) {
                    continue;
                }
                [$userId, $permissionId] = explode(':', $key);
                $db->table('model_has_permissions')->insert([
                    'permission_id' => (int) $permissionId, 'model_type' => 'shop_user',
                    'model_id' => (int) $userId,
                    'assigned_at' => $periods[0][0], 'expires_at' => $periods[0][1],
                ]);
            }
            // Initialize the verified owner's NEW module rights once during conversion.
            $tenant = tenancy()->tenant;
            $owner = $tenant?->owner;
            if ($owner === null) {
                throw new LogicException('Propriétaire central introuvable.');
            }
            $localOwner = User::where('central_user_uuid', $owner->uuid)->lockForUpdate()->firstOrFail();
            TenantPermissionCatalog::seed();
            $permissions = \App\Models\Tenant\Permissions\Permission::whereIn('name', array_keys(TenantPermissionCatalog::ALL))->get();
            foreach ($permissions as $permission) {
                if (! $db->table('model_has_permissions')->where('model_type', 'shop_user')
                    ->where('model_id', $localOwner->id)->where('permission_id', $permission->id)->exists()) {
                    $db->table('model_has_permissions')->insert([
                        'permission_id' => $permission->id, 'model_type' => 'shop_user',
                        'model_id' => $localOwner->id, 'assigned_at' => now(),
                        'expires_at' => now()->addDays(\App\Models\Shared\Authorization\PermissionAssignment::NEVER_EXPIRES_DAYS),
                    ]);
                }
            }
            // Only activate after the entire tenant conversion succeeded.
            $db->table('tenant_authorization_state')->where('id', 1)->update([
                'individual_only' => true, 'converted_at' => now(), 'updated_at' => now(),
            ]);
            activity()->event('tenant_individual_authorization_converted')
                ->withProperties(['converted_grants' => $diagnostic['grants']])
                ->log('Conversion des autorisations Tenant');
            $db->afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
            return $diagnostic;
        }, 3);
    }

    /** @return array<string,list<array{string,string}>> */
    private function inheritances(): array
    {
        $rows = DB::connection('tenant')->table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->join('role_has_permissions as rp', 'rp.role_id', '=', 'mr.role_id')
            ->where('mr.model_type', 'shop_user')
            ->get(['mr.model_id', 'rp.permission_id', 'mr.assigned_at', 'rp.duration_days', 'r.is_super_admin']);
        // A legacy super administrator has implicit rights; refuse conversion until explicitly resolved.
        $superAdmins = DB::connection('tenant')->table('model_has_roles as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->where('mr.model_type', 'shop_user')->where('r.is_super_admin', 1)->exists();
        if ($superAdmins) {
            throw new LogicException('Rôle Super Admin hérité détecté. Attribution individuelle à décider manuellement avant conversion.');
        }
        $out = [];
        foreach ($rows as $row) {
            $from = CarbonImmutable::parse($row->assigned_at);
            $until = $from->addDays((int) $row->duration_days);
            $out[$row->model_id.':'.$row->permission_id][] = [$from->toDateTimeString(), $until->toDateTimeString()];
        }
        return $out;
    }
}
