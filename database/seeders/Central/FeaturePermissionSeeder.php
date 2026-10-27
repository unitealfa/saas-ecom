<?php

namespace Database\Seeders\Central;

use App\Models\Central\Permissions\Permission;
use App\Services\Central\Roles\CentralRoleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

class FeaturePermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Feature permissions require the central context.');
        }
        DB::transaction(function (): void {
            foreach (CentralRoleCatalog::FEATURE_PERMISSIONS as $name => $label) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => Permission::GUARD], ['label' => $label]);
            }
            DB::afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        });
    }
}
