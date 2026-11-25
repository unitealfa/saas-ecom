<?php

namespace App\Events\Tenant\Auth;

final class EmployeePasswordResetRequested
{
    public function __construct(
        public readonly string $tenantUuid,
        public readonly string $userUuid,
        public readonly string $email,
        public readonly string $url,
        public readonly int $expiresAt,
    ) {}
}
