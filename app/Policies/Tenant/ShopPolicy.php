<?php

namespace App\Policies\Tenant;

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Users\User;

class ShopPolicy
{
    public function view(User $actor, Shop $shop): bool
    {
        return $this->allows($actor, $shop, 'shop.profile.view');
    }

    public function update(User $actor, Shop $shop): bool
    {
        return $this->allows($actor, $shop, 'shop.profile.update');
    }

    public function branding(User $actor, Shop $shop): bool
    {
        return $this->allows($actor, $shop, 'shop.branding.update');
    }

    public function allows(User $actor, Shop $shop, string $ability): bool
    {
        $tenant = tenancy()->tenant;

        return tenancy()->initialized
            && $tenant instanceof Tenant
            && $shop->tenant_uuid === $tenant->uuid
            && (int) $shop->singleton === 1
            && $actor->status === StatusEnum::ACTIVE
            && $actor->membership_status === MembershipStatusEnum::ACTIVE
            && $actor->joined_at !== null
            && $actor->email_verified_at !== null
            && ! $actor->trashed()
            && $actor->can($ability);
    }
}
