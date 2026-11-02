<?php

namespace App\Services\Central\Shipping;

use App\Models\Central\Shipping\PickupPoint;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

class PickupPointCatalog
{
    private const int TTL = 43200;

    private const array FIELDS = ['external_code', 'name', 'address', 'phone', 'map_url', 'reference_version'];

    public function __construct(private readonly CacheManager $cache) {}

    /**
     * Présentation seulement : la disponibilité pour un envoi doit être contrôlée en base.
     *
     * @return array<string, mixed>
     */
    public function find(string $uuid): array
    {
        $read = static function () use ($uuid): array {
            $point = PickupPoint::with(['carrier', 'province', 'municipality'])->where('uuid', $uuid)->firstOrFail();

            return [
                'uuid' => $point->uuid,
                'carrier_uuid' => $point->carrier->uuid,
                'province_uuid' => $point->province->uuid,
                'municipality_uuid' => $point->municipality?->uuid,
                ...$point->only(self::FIELDS),
            ];
        };

        // Dans une transaction, on lit toujours la base : le cache n'a pas encore vu les écritures en cours.
        if ($this->connection()->transactionLevel() > 0) {
            return $read();
        }

        $store = $this->store();

        return $store->remember($store->get('version', 'initial').':'.$uuid, self::TTL, $read);
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
            'path' => base_path('storage/framework/cache/central-pickup-points/'.$identity),
        ]);
    }

    private function connection(): Connection
    {
        return (new PickupPoint)->getConnection();
    }
}
