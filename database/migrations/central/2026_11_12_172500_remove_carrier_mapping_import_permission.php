<?php

use App\Models\Central\Permissions\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $permission = Permission::forCentralGuard()->where('name', 'saas.carrier-mappings.import')->lockForUpdate()->first();
            if ($permission === null) {
                return;
            }

            foreach (['role_has_permissions', 'model_has_permissions'] as $table) {
                DB::table(config('permission.table_names.'.$table))->where('permission_id', $permission->id)->delete();
            }
            $permission->delete();
            DB::afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        });
    }

    public function down(): void
    {
        // Le retrait de la fonctionnalité ne doit pas recréer une permission inutilisable.
    }
};
