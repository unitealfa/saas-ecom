<?php

use App\Models\Central\Permissions\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $permission = Permission::forCentralGuard()->where('name', 'saas.geography.import')->lockForUpdate()->first();
            if ($permission === null) {
                return;
            }
            foreach (['role_has_permissions', 'model_has_permissions'] as $table) {
                if (DB::table(config('permission.table_names.'.$table))->where('permission_id', $permission->id)->exists()) {
                    throw new LogicException('Remove geographic import assignments before deleting this obsolete permission.');
                }
            }
            $permission->delete();
            DB::afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The removed functionality and its permissions must not be restored by rollback.
    }
};
