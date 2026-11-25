<?php

namespace App\Events\Tenant\Users;

final class WelcomeEmployeeMailRequested
{
    public function __construct(
        public readonly string $tenantUuid,
        public readonly string $userUuid,
        public readonly string $email,
        public readonly string $shopName,
        public readonly string $url,
    ) {}
}
