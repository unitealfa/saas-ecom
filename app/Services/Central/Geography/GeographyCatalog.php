<?php

namespace App\Services\Central\Geography;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Geography\GeographicArea;
use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class GeographyCatalog
{
    private const int TTL = 43200;

    private const int PER_PAGE = 30;

    public function __construct(private readonly CacheManager $cache) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listing(array $filters): array
    {
        // Même filtre, même clé de cache, quel que soit l'ordre des paramètres.
        ksort($filters);

        return $this->remember('list:'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR)), function () use ($filters): array {
            $page = GeographicArea::selectable()->with('parent')
                ->search($filters['search'] ?? null)
                ->when($filters['type'] ?? null, fn (Builder $query, mixed $type) => $query->where('type', $type))
                ->when($filters['parent_uuid'] ?? null, fn (Builder $query, string $uuid) => $query->whereRelation('parent', 'uuid', $uuid))
                ->orderBy('code')
                ->paginate(self::PER_PAGE, ['*'], 'page', (int) ($filters['page'] ?? 1));

            return [
                'data' => $page->getCollection()->map($this->row(...))->all(),
                'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            ];
        });
    }

    /** @return array<string, mixed> */
    public function find(string $uuid): array
    {
        return $this->remember('zone:'.$uuid, fn (): array => $this->row(
            GeographicArea::selectable()->with('parent')->where('uuid', $uuid)->firstOrFail(),
        ));
    }

    public function belongsTo(string $communeUuid, string $provinceUuid): bool
    {
        return GeographicArea::selectable()
            ->where('uuid', $communeUuid)
            ->where('type', GeoZoneTypeEnum::MUNICIPALITY)
            ->whereRelation('parent', 'uuid', $provinceUuid)
            ->exists();
    }

    /** Change la version du cache : toutes les entrées existantes deviennent inaccessibles. */
    public function invalidate(): void
    {
        $this->store()->forever('version', (string) Str::uuid());
    }

    /** @return array<string, mixed> */
    private function row(GeographicArea $area): array
    {
        return [
            'uuid' => $area->uuid,
            'type' => $area->type->value,
            'code' => $area->code,
            'name_fr' => $area->name_fr,
            'name_ar' => $area->name_ar,
            'parent_uuid' => $area->parent?->uuid,
        ];
    }

    /**
     * @param  Closure(): array<string, mixed>  $read
     * @return array<string, mixed>
     */
    private function remember(string $key, Closure $read): array
    {
        // Dans une transaction, on lit toujours la base : le cache n'a pas encore vu les écritures en cours.
        if ($this->connection()->transactionLevel() > 0) {
            return $read();
        }

        $store = $this->store();

        return $store->remember($store->get('version', 'initial').':'.$key, self::TTL, $read);
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
            'path' => base_path('storage/framework/cache/central-geography/'.$identity),
        ]);
    }

    private function connection(): Connection
    {
        return (new GeographicArea)->getConnection();
    }
}
