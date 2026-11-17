<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Users\TeamInvitation;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;

final class TeamInvitationPolicy
{
    public function viewAny(User $actor): bool { return TenantAccess::allows($actor, 'shop.users.view'); }
    public function create(User $actor): bool { return TenantAccess::allows($actor, 'shop.team.invite'); }
    public function revoke(User $actor, TeamInvitation $invite): bool
    {
        return TenantAccess::allows($actor, 'shop.team.invitations.revoke') && $invite->accepted_at === null && $invite->revoked_at === null;
    }
    public function resend(User $actor, TeamInvitation $invite): bool
    {
        return TenantAccess::allows($actor, 'shop.team.invitations.resend') && $invite->accepted_at === null && $invite->revoked_at === null;
    }
}
