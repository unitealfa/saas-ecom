<?php

namespace App\Services\Tenant\Shop;

use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Shop\Shop;
use Illuminate\Contracts\Cache\Repository;
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
        $this->invalidateStore(Cache::store('file'), $this->prefix());
    }

    public function invalidateAfterCommit(Shop $shop): void
    {
        $cache = Cache::store('file');
        $prefix = $this->prefix();
        $shop->getConnection()->afterCommit(fn () => $this->invalidateStore($cache, $prefix));
    }

    private function invalidateStore(Repository $cache, string $prefix): void
    {
        $old = (string) $cache->get($prefix.':version', 'initial');
        $cache->forever($prefix.':version', (string) Str::uuid());
        $cache->forget($prefix.':'.$old);
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $shop = Shop::query()->with(['logoMedia', 'faviconMedia'])->sole();
        abort_unless($shop->tenant_uuid === tenant()->uuid, 403);

        $addresses = $shop->addresses()->where('visible', true)->orderBy('position')->get()->keyBy('id');

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
            'addresses' => $addresses->values()
                ->map(fn ($a): array => ['uuid' => $a->uuid, 'label' => $a->label, 'province_uuid' => $a->province_uuid,
                    'municipality_uuid' => $a->municipality_uuid, 'is_primary' => $a->is_primary, 'payload' => $a->payload])->all(),
            'social_links' => $shop->socialLinks()->where('visible', true)->orderBy('position')->get()
                ->filter(fn ($link): bool => $link->shop_address_id === null
                    || $addresses->has($link->shop_address_id))
                ->map(fn ($link): array => ['uuid' => $link->uuid, 'label' => $link->label,
                    'address_uuid' => $link->shop_address_id === null ? null : $addresses->get($link->shop_address_id)?->uuid,
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
        $connection = (new Shop)->getConnection();

        return 'public-shop-profile:v1:'.hash('sha256', json_encode([
            $tenant->uuid, $connection->getConfig('host'), $connection->getConfig('port'), $connection->getDatabaseName(),
        ], JSON_THROW_ON_ERROR));
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
