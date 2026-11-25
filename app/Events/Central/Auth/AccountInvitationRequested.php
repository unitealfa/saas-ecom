<?php

namespace App\Events\Central\Auth;

final class AccountInvitationRequested
{
    public function __construct(
        public readonly string $userUuid,
        public readonly string $email,
        public readonly string $token,
        public readonly string $url,
        public readonly int $expiresAt,
        public readonly ?string $actorUuid,
    ) {}
}
