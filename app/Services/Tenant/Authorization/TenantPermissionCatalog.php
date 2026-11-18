<?php

namespace App\Services\Tenant\Authorization;

use App\Models\Tenant\Permissions\Permission;

final class TenantPermissionCatalog
{
    public const array ALL = [
        'shop.roles.view' => 'Consulter les profils locaux',
        'shop.roles.create' => 'Créer un profil local',
        'shop.roles.update' => 'Modifier un profil local',
        'shop.roles.delete' => 'Supprimer un profil local inutilisé',
        'shop.roles.assign' => 'Attribuer ou retirer un profil',
        'shop.permissions.view' => 'Consulter le catalogue des permissions',
        'shop.users.view' => 'Consulter les membres de l’équipe',
        'shop.users.permissions.update' => 'Attribuer ou retirer un droit individuel',
        'shop.users.permissions.renew' => 'Renouveler explicitement un droit',
        'shop.team.invite' => 'Inviter un membre',
        'shop.team.invitations.revoke' => 'Révoquer une invitation',
        'shop.team.invitations.resend' => 'Renvoyer une invitation',
        'shop.team.memberships.update' => 'Suspendre ou révoquer un membre',
    ];

    public static function seed(): void
    {
        abort_unless(tenancy()->initialized, 403);
        foreach (self::ALL as $code => $label) {
            Permission::firstOrCreate(['name' => $code, 'guard_name' => Permission::GUARD], ['label' => $label]);
        }
    }
}
