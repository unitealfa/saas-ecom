<?php

namespace App\Services\Central\Countries;

use App\Models\Central\Country;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CountryCatalog
{
    public function __construct(private CacheManager $cache) {}

    /** @return Collection<int, Country> */
    public function active(): Collection
    {
        return $this->available();
    }

    /** @return Collection<int, Country> */
    public function forProfile(?int $currentId): Collection
    {
        return $this->available($currentId);
    }

    /** @return Collection<int, Country> */
    public function forPhone(?int $currentId): Collection
    {
        return $this->available($currentId, includeArchivedCurrent: true);
    }

    public function invalidate(): void
    {
        $this->store()->forever('countries:version', (string) Str::uuid());
    }

    /** @return Collection<int, Country> */
    private function available(?int $currentId = null, bool $includeArchivedCurrent = false): Collection
    {
        return $this->all()->filter(fn (Country $country): bool =>
            (! $country->trashed() && ($country->is_active || $country->id === $currentId))
            || ($includeArchivedCurrent && $country->id === $currentId)
        )->values();
    }

    /** @return Collection<int, Country> */
    private function all(): Collection
    {
        // Never publish uncommitted reference data to the shared cache.
        if ((new Country)->getConnection()->transactionLevel() > 0) {
            return Country::withTrashed()->orderBy('name_fr')->orderBy('id')->get();
        }

        $store = $this->store();
        $version = $store->get('countries:version', 'initial');

        $rows = $store->remember('countries:v2:'.$version, 60 * 60 * 12,
            fn (): array => Country::withTrashed()->orderBy('name_fr')->orderBy('id')->get()
                ->map(fn (Country $country): array => $country->getAttributes())->all()
        );

        return Country::hydrate($rows);
    }

    private function store(): Repository
    {
        $connection = (new Country)->getConnection();
        $identity = json_encode([
            $connection->getDriverName(), $connection->getConfig('host'),
            $connection->getConfig('port'), $connection->getDatabaseName(),
        ], JSON_THROW_ON_ERROR);

        // A dedicated central store must not inherit the current tenant's cache path.
        return $this->cache->build([
            'driver' => 'file',
            'path' => base_path('storage/framework/cache/central-reference/'.hash('sha256', $identity)),
        ]);
    }
}
