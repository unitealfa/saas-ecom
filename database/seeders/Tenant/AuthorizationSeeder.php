<?php

namespace Database\Seeders\Tenant;

use App\Services\Tenant\Authorization\TenantPermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

final class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(tenancy()->initialized, 403);
        DB::connection('tenant')->transaction(function (): void {
            TenantPermissionCatalog::seed();
            // Only a genuinely unused freshly provisioned shop is immediately switched.
            if (Schema::connection('tenant')->hasTable('tenant_authorization_state')
                && ! DB::connection('tenant')->table('model_has_roles')->exists()
                && ! DB::connection('tenant')->table('model_has_permissions')->exists()) {
                DB::connection('tenant')->table('tenant_authorization_state')->where('id', 1)
                    ->whereNull('converted_at')->update(['individual_only' => true,
                        'converted_at' => now(), 'updated_at' => now()]);
            }
            DB::connection('tenant')->afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        }, 3);
    }
}
