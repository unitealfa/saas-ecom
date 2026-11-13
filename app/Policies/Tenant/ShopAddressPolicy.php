<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Shop\ShopAddress;
use App\Models\Tenant\Users\User;

class ShopAddressPolicy
{
    public function viewAny(User $actor, Shop $shop): bool
    {
        return app(ShopPolicy::class)->allows($actor, $shop, 'shop.addresses.view');
    }

    public function create(User $actor, Shop $shop): bool
    {
        return app(ShopPolicy::class)->allows($actor, $shop, 'shop.addresses.create');
    }

    public function update(User $actor, ShopAddress $address): bool
    {
        $shop = Shop::query()->whereKey($address->shop_id)->first();

        return $shop !== null && ! $address->trashed()
            && app(ShopPolicy::class)->allows($actor, $shop, 'shop.addresses.update');
    }

    public function archive(User $actor, ShopAddress $address): bool
    {
        $shop = Shop::query()->whereKey($address->shop_id)->first();

        return $shop !== null && ! $address->trashed()
            && app(ShopPolicy::class)->allows($actor, $shop, 'shop.addresses.archive');
    }
}
