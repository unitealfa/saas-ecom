<?php

namespace Database\Seeders\Central;

use App\Models\Central\Permission;
use App\Services\Central\Roles\CentralRoleCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class DomainPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new \LogicException('Domain permissions must be seeded in the central context.');
        }
        DB::transaction(function (): void {
            foreach (CentralRoleCatalog::DOMAIN_PERMISSIONS as $name => $label) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => Permission::GUARD], ['label' => $label]);
            }
            DB::afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        });
    }
}
