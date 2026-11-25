<?php

namespace App\Events\Tenant\Users;

final class TeamInvitationMailRequested
{
    public function __construct(
        public readonly string $tenantUuid,
        public readonly string $invitationUuid,
        public readonly string $email,
        public readonly string $url,
        public readonly string $shopName,
        public readonly int $expiresAt,
        public readonly ?string $actorUuid,
        public readonly ?string $ownerUuid,
    ) {}
}
