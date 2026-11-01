<?php

namespace App\Services\Central\Shipping;

use App\Models\Central\Shipping\ShippingCarrier;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Support\Str;

class ShippingCarrierCatalog
{
    private const int TTL = 43200;

    public function __construct(private readonly CacheManager $cache) {}

    /** @return list<array{uuid: string, code: string, name: string}> */
    public function active(): array
    {
        $read = static fn (): array => array_values(ShippingCarrier::active()->orderBy('name')->orderBy('id')
            ->get(['uuid', 'code', 'name'])
            ->map(static fn (ShippingCarrier $carrier): array => $carrier->only(['uuid', 'code', 'name']))->all());

        if ((new ShippingCarrier)->getConnection()->transactionLevel() > 0) {
            return $read();
        }

        $store = $this->store();

        return $store->remember($store->get('version', 'initial').':active', self::TTL, $read);
    }

    public function invalidate(): void
    {
        $this->store()->forever('version', (string) Str::uuid());
    }

    private function store(): Repository
    {
        $connection = (new ShippingCarrier)->getConnection();
        $identity = hash('sha256', json_encode([
            $connection->getConfig('host'), $connection->getConfig('port'), $connection->getDatabaseName(),
        ], JSON_THROW_ON_ERROR));

        return $this->cache->build([
            'driver' => 'file',
            'path' => base_path('storage/framework/cache/central-carriers/'.$identity),
        ]);
    }
}
