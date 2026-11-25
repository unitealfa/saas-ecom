<?php

namespace App\Events\Tenant\Users;

final class EmployeeEmailConfirmationRequested
{
    public function __construct(
        public readonly string $tenantUuid,
        public readonly string $userUuid,
        public readonly string $email,
        public readonly string $url,
        public readonly int $expiresAt,
    ) {}
}
