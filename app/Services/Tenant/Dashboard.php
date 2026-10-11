<?php

namespace App\Services\Tenant;

use App\DTOs\Tenant\DashboardData;
use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Users\StatusEnum;
use App\Models\Central\Tenant;
use App\Models\Tenant\Shop;
use App\Models\Tenant\User;
use Stancl\Tenancy\Tenancy;

class Dashboard
{
    public function __construct(private Tenancy $tenancy) {}

    public function forUser(User $user): DashboardData
    {
        $tenant = $this->tenancy->tenant;

        abort_unless($this->tenancy->initialized && $tenant instanceof Tenant, 403);
        abort_unless(
            $user->status === StatusEnum::ACTIVE
            && $user->membership_status === MembershipStatusEnum::ACTIVE
            && $user->getAttribute('joined_at') !== null
            && $user->getAttribute('email_verified_at') !== null,
            403,
        );

        $shop = Shop::query()->sole(['shop_name', 'tenant_uuid']);

        abort_unless($shop->getAttribute('tenant_uuid') === $tenant->uuid, 403);

        return new DashboardData(
            shopName: (string) $shop->getAttribute('shop_name'),
            userName: trim((string) $user->getAttribute('first_name').' '.(string) $user->getAttribute('last_name')),
        );
    }
}
