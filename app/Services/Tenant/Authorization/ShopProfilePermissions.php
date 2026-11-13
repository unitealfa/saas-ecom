<?php

namespace App\Services\Tenant\Authorization;

use App\Models\Tenant\Permissions\Permission;

final class ShopProfilePermissions
{
    public static function seed(): void
    {
        abort_unless(tenancy()->initialized, 403);
        foreach (self::ALL as $name => $label) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => Permission::GUARD], ['label' => $label]);
        }
    }

    public const array ALL = [
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
}
