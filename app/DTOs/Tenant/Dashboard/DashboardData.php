<?php

namespace App\DTOs\Tenant\Dashboard;

final readonly class DashboardData
{
    public function __construct(public string $shopName, public string $userName) {}
}
