<?php

namespace App\Services\Tenant\Dashboard;

use App\DTOs\Tenant\Dashboard\DashboardData;
use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Users\User;
use Stancl\Tenancy\Tenancy;

class Dashboard
{
    public function __construct(private Tenancy $tenancy) {}

    public function forUser(User $user): DashboardData
    {
        $tenant = $this->currentTenant();

        abort_unless($this->isActiveMember($user), 403);

        $shop = Shop::query()->sole(['shop_name', 'tenant_uuid']);

        abort_unless($shop->getAttribute('tenant_uuid') === $tenant->uuid, 403);

        return new DashboardData(
            shopName: (string) $shop->getAttribute('shop_name'),
            userName: trim($user->getAttribute('first_name').' '.$user->getAttribute('last_name')),
        );
    }

    private function currentTenant(): Tenant
    {
        $tenant = $this->tenancy->tenant;

        abort_unless($this->tenancy->initialized && $tenant instanceof Tenant, 403);

        return $tenant;
    }

    private function isActiveMember(User $user): bool
    {
        return $user->status === StatusEnum::ACTIVE
            && $user->membership_status === MembershipStatusEnum::ACTIVE
            && $user->getAttribute('joined_at') !== null
            && $user->getAttribute('email_verified_at') !== null;
    }
}
