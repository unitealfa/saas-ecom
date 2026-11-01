<?php

namespace App\Services\Central\Shipping;

use App\Models\Central\Shipping\CarrierGeoMapping;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Support\Str;

class CarrierGeoMappingCatalog
{
    private const int TTL = 43200;

    public function __construct(private readonly CacheManager $cache) {}

    /** Présentation seulement : la disponibilité pour un envoi doit être contrôlée en base.
     * @return array<string, mixed>
     */
    public function find(string $uuid): array
    {
        $read = static function () use ($uuid): array {
            $mapping = CarrierGeoMapping::with(['carrier', 'area'])->where('uuid', $uuid)->firstOrFail();

            return ['uuid' => $mapping->uuid, 'carrier_uuid' => $mapping->carrier->uuid,
                'geographic_area_uuid' => $mapping->area->uuid,
                ...$mapping->only(['external_code', 'external_name', 'external_province_code', 'mapping_version'])];
        };
        if ((new CarrierGeoMapping)->getConnection()->transactionLevel() > 0) {
            return $read();
        }
        $store = $this->store();

        return $store->remember($store->get('version', 'initial').':'.$uuid, self::TTL, $read);
    }

    public function invalidate(): void
    {
        $this->store()->forever('version', (string) Str::uuid());
    }

    private function store(): Repository
    {
        $connection = (new CarrierGeoMapping)->getConnection();
        $identity = hash('sha256', json_encode([
            $connection->getConfig('host'), $connection->getConfig('port'), $connection->getDatabaseName(),
        ], JSON_THROW_ON_ERROR));

        return $this->cache->build(['driver' => 'file',
            'path' => base_path('storage/framework/cache/central-carrier-mappings/'.$identity)]);
    }
}
