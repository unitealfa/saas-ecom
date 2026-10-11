<?php

namespace App\DTOs\Central;

use App\DTOs\Tenant\ShopSettings;

final readonly class TenantData
{
    public function __construct(
        public string $shopName,
        public string $slug,
        public string $creationKey,
        public ShopSettings $settings,
    ) {}

    public function fingerprint(string $ownerUuid): string
    {
        return hash('sha256', json_encode([
            $ownerUuid, $this->shopName, $this->slug, $this->settings->toArray(),
        ], JSON_THROW_ON_ERROR));
    }
}
