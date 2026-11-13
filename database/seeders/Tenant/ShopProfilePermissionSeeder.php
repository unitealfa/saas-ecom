<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Permissions\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

/** Additive and idempotent: neither modifies existing labels nor assigns rights. */
class ShopProfilePermissionSeeder extends Seeder
{
    private const array RIGHTS = [
        'shop.profile.view' => 'Consulter le profil de la boutique',
        'shop.profile.update' => 'Modifier le profil de la boutique',
        'shop.branding.update' => 'Modifier le logo et le favicon',
        'shop.addresses.view' => 'Consulter les adresses publiques',
        'shop.addresses.create' => 'Créer une adresse publique',
        'shop.addresses.update' => 'Modifier une adresse publique',
        'shop.addresses.archive' => 'Archiver une adresse publique',
        'shop.social-links.view' => 'Consulter les liens sociaux',
        'shop.social-links.create' => 'Créer un lien social',
        'shop.social-links.update' => 'Modifier un lien social',
        'shop.social-links.archive' => 'Archiver un lien social',
    ];

    public function run(): void
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Shop profile permissions must be seeded inside a tenant database.');
        }

        DB::transaction(function (): void {
            foreach (self::RIGHTS as $name => $label) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => Permission::GUARD], ['label' => $label]);
            }

            $registrar = app(PermissionRegistrar::class);
            DB::afterCommit(fn () => $registrar->forgetCachedPermissions());
        }, 3);
    }
}
