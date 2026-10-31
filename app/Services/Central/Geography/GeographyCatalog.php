<?php

namespace App\Services\Central\Geography;

use App\Models\Central\Geography\GeographicArea;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class GeographyCatalog
{
    private const int TTL = 43200;

    public function __construct(private CacheManager $cache) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listing(array $filters): array
    {
        $read = function () use ($filters): array {
            $query = GeographicArea::selectable()->with('parent')->search($filters['search'] ?? null)
                ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type))
                ->when($filters['parent_uuid'] ?? null, fn (Builder $q, $uuid) => $q->whereHas('parent', fn (Builder $p) => $p->where('uuid', $uuid)));
            $page = $query->orderBy('code')->paginate(30, ['*'], 'page', (int) ($filters['page'] ?? 1));

            return ['data' => $page->getCollection()->map($this->row(...))->all(), 'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]];
        };

        return $this->remember('list:'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR)), $read);
    }

    /** @return array<string, mixed> */
    public function find(string $uuid): array
    {
        return $this->remember('zone:'.$uuid, fn (): array => $this->row(GeographicArea::selectable()->with('parent')->where('uuid', $uuid)->firstOrFail()));
    }

    public function belongsTo(string $communeUuid, string $provinceUuid): bool
    {
        return GeographicArea::selectable()->where('uuid', $communeUuid)->where('type', 2)
            ->whereHas('parent', fn (Builder $parent) => $parent->where('uuid', $provinceUuid))->exists();
    }

    public function invalidate(): void
    {
        $this->store()->forever('version', (string) Str::uuid());
    }

    /** @return array<string, mixed> */
    private function row(GeographicArea $area): array
    {
        return ['uuid' => $area->uuid, 'type' => $area->type->value, 'code' => $area->code,
            'name_fr' => $area->name_fr, 'name_ar' => $area->name_ar, 'parent_uuid' => $area->parent?->uuid];
    }

    /** @param \Closure(): array<string, mixed> $read
     * @return array<string, mixed>
     */
    private function remember(string $key, \Closure $read): array
    {
        if ((new GeographicArea)->getConnection()->transactionLevel() > 0) {
            return $read();
        }
        $store = $this->store();

        return $store->remember($store->get('version', 'initial').':'.$key, self::TTL, $read);
    }

    private function store(): Repository
    {
        $connection = (new GeographicArea)->getConnection();
        $identity = hash('sha256', json_encode([$connection->getConfig('host'), $connection->getConfig('port'), $connection->getDatabaseName()], JSON_THROW_ON_ERROR));

        return $this->cache->build(['driver' => 'file', 'path' => base_path('storage/framework/cache/central-geography/'.$identity)]);
    }
}
