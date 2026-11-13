<?php

namespace App\Services\Tenant\Shop;

use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Shop\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;

/** Public fields only; cache is scoped to a real tenant UUID and database. */
class ShopProfileCatalog
{
    private const int TTL_SECONDS = 3600;

    /** @return array<string, mixed> */
    public function publicProfile(): array
    {
        $this->assertTenant();
        $shop = new Shop;
        if ($shop->getConnection()->transactionLevel() > 0) {
            return $this->read();
        }
        $cache = Cache::store('file');
        $version = (string) $cache->get($this->versionKey(), 'initial');

        return $cache->remember($this->dataKey($version), self::TTL_SECONDS, fn (): array => $this->read());
    }

    public function invalidate(): void
    {
        $this->assertTenant();
        $cache = Cache::store('file');
        $old = (string) $cache->get($this->versionKey(), 'initial');
        $cache->forever($this->versionKey(), (string) Str::uuid());
        $cache->forget($this->dataKey($old));
    }

    public function invalidateAfterCommit(Shop $shop): void
    {
        $shop->getConnection()->afterCommit(fn () => $this->invalidate());
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $shop = Shop::query()->with(['logoMedia', 'faviconMedia'])->sole();
        abort_unless($shop->tenant_uuid === tenant()->uuid, 403);

        return [
            'shop_name' => $shop->shop_name,
            'description' => $shop->description,
            'about' => $shop->about,
            'business_type' => $shop->business_type,
            'contact_email' => $shop->contact_email,
            'contact_phone' => $shop->contact_phone,
            'contact_whatsapp' => $shop->contact_whatsapp,
            'locale' => $shop->locale,
            'timezone' => $shop->timezone,
            'theme_code' => $shop->theme_code,
            'colors' => $shop->colors,
            'logo_url' => $shop->logoMedia === null ? null : route('tenant.shop-profile.branding', ['kind' => 'logo', 'v' => $shop->logoMedia->uuid], false),
            'favicon_url' => $shop->faviconMedia === null ? null : route('tenant.shop-profile.branding', ['kind' => 'favicon', 'v' => $shop->faviconMedia->uuid], false),
            'addresses' => $shop->addresses()->where('visible', true)->orderBy('position')->get()
                ->map(fn ($a): array => ['uuid' => $a->uuid, 'label' => $a->label, 'province_uuid' => $a->province_uuid,
                    'municipality_uuid' => $a->municipality_uuid, 'is_primary' => $a->is_primary, 'payload' => $a->payload])->all(),
            'social_links' => $shop->socialLinks()->where('visible', true)->orderBy('position')->get()
                ->filter(fn ($link): bool => $link->shop_address_id === null
                    || $shop->addresses()->whereKey($link->shop_address_id)->where('visible', true)->exists())
                ->map(fn ($link): array => ['uuid' => $link->uuid, 'label' => $link->label,
                    'address_uuid' => $link->shop_address_id === null ? null : $shop->addresses()->whereKey($link->shop_address_id)->value('uuid'),
                    'payload' => $link->payload])->values()->all(),
        ];
    }

    private function versionKey(): string
    {
        return $this->prefix().':version';
    }

    private function dataKey(string $version): string
    {
        return $this->prefix().':'.$version;
    }

    private function prefix(): string
    {
        $tenant = $this->assertTenant();

        return 'public-shop-profile:v1:'.hash('sha256', $tenant->uuid.'|'.(new Shop)->getConnection()->getDatabaseName());
    }

    private function assertTenant(): Tenant
    {
        $tenant = tenancy()->tenant;
        if (! tenancy()->initialized || ! $tenant instanceof Tenant) {
            throw new LogicException('Shop profile reads require tenant context.');
        }

        return $tenant;
    }
}
