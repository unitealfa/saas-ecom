<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Shop\ShopSocialLink;
use App\Models\Tenant\Users\User;

class ShopSocialLinkPolicy
{
    public function viewAny(User $actor, Shop $shop): bool
    {
        return app(ShopPolicy::class)->allows($actor, $shop, 'shop.social-links.view');
    }

    public function create(User $actor, Shop $shop): bool
    {
        return app(ShopPolicy::class)->allows($actor, $shop, 'shop.social-links.create');
    }

    public function update(User $actor, ShopSocialLink $link): bool
    {
        $shop = Shop::query()->whereKey($link->shop_id)->first();

        return $shop !== null && ! $link->trashed()
            && app(ShopPolicy::class)->allows($actor, $shop, 'shop.social-links.update');
    }

    public function archive(User $actor, ShopSocialLink $link): bool
    {
        $shop = Shop::query()->whereKey($link->shop_id)->first();

        return $shop !== null && ! $link->trashed()
            && app(ShopPolicy::class)->allows($actor, $shop, 'shop.social-links.archive');
    }
}
