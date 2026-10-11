<?php

namespace App\DTOs\Tenant;

final readonly class DashboardData
{
    public function __construct(public string $shopName, public string $userName) {}
}
