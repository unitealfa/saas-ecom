<?php

namespace App\Services\Central\Shipping;

use App\Models\Central\Shipping\ShippingCarrier;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

class ShippingCarrierCatalog
{
    private const int TTL = 43200;

    private const array COLUMNS = ['uuid', 'code', 'name'];

    public function __construct(private readonly CacheManager $cache) {}

    /** @return list<array{uuid: string, code: string, name: string}> */
    public function active(): array
    {
        $read = static fn (): array => array_values(ShippingCarrier::active()
            ->orderBy('name')->orderBy('id')
            ->get(self::COLUMNS)
            ->map(static fn (ShippingCarrier $carrier): array => $carrier->only(self::COLUMNS))
            ->all());

        // Dans une transaction, on lit toujours la base : le cache n'a pas encore vu les écritures en cours.
        if ($this->connection()->transactionLevel() > 0) {
            return $read();
        }

        $store = $this->store();

        return $store->remember($store->get('version', 'initial').':active', self::TTL, $read);
    }

    /** Change la version du cache : toutes les entrées existantes deviennent inaccessibles. */
    public function invalidate(): void
    {
        $this->store()->forever('version', (string) Str::uuid());
    }

    /** Un cache fichier par base de données, pour ne jamais mélanger deux environnements. */
    private function store(): Repository
    {
        $connection = $this->connection();
        $identity = hash('sha256', json_encode([
            $connection->getConfig('host'),
            $connection->getConfig('port'),
            $connection->getDatabaseName(),
        ], JSON_THROW_ON_ERROR));

        return $this->cache->build([
            'driver' => 'file',
            'path' => base_path('storage/framework/cache/central-carriers/'.$identity),
        ]);
    }

    private function connection(): Connection
    {
        return (new ShippingCarrier)->getConnection();
    }
}
