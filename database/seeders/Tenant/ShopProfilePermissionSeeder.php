<?php

namespace Database\Seeders\Tenant;

use App\Services\Tenant\Authorization\ShopProfilePermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

/** Additive and idempotent: neither modifies existing labels nor assigns rights. */
class ShopProfilePermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Shop profile permissions must be seeded inside a tenant database.');
        }

        DB::transaction(function (): void {
            ShopProfilePermissions::seed();

            $registrar = app(PermissionRegistrar::class);
            DB::afterCommit(fn () => $registrar->forgetCachedPermissions());
        }, 3);
    }
}
