<?php

namespace App\Services\Central\Countries;

use App\Models\Central\Countries\Country;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CountryCatalog
{
    private const int TTL_SECONDS = 43200;

    private const string VERSION_KEY = 'countries:version';

    private const string ROWS_PREFIX = 'countries:v2:';

    private const string INITIAL_VERSION = 'initial';

    private ?Repository $store = null;

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
        $store = $this->store();
        $previous = $store->get(self::VERSION_KEY, self::INITIAL_VERSION);

        $store->forever(self::VERSION_KEY, (string) Str::uuid());
        $store->forget(self::ROWS_PREFIX.$previous);
    }

    /** @return Collection<int, Country> */
    private function available(?int $currentId = null, bool $includeArchivedCurrent = false): Collection
    {
        return $this->all()
            ->filter(fn (Country $country): bool => $this->isVisible($country, $currentId, $includeArchivedCurrent))
            ->values();
    }

    private function isVisible(Country $country, ?int $currentId, bool $includeArchivedCurrent): bool
    {
        $isCurrent = $country->id === $currentId;

        if ($includeArchivedCurrent && $isCurrent) {
            return true;
        }

        return ! $country->trashed() && ($country->is_active || $isCurrent);
    }

    /** @return Collection<int, Country> */
    private function all(): Collection
    {
        if ($this->connection()->transactionLevel() > 0) {
            return $this->fromDatabase();
        }

        $store = $this->store();
        $version = $store->get(self::VERSION_KEY, self::INITIAL_VERSION);

        $rows = $store->remember(
            self::ROWS_PREFIX.$version,
            self::TTL_SECONDS,
            fn (): array => $this->fromDatabase()
                ->map(fn (Country $country): array => $country->getAttributes())
                ->all(),
        );

        return Country::hydrate($rows);
    }

    /** @return Collection<int, Country> */
    private function fromDatabase(): Collection
    {
        return Country::withTrashed()->orderBy('name_fr')->orderBy('id')->get();
    }

    private function connection(): Connection
    {
        return DB::connection((new Country)->getConnectionName());
    }

    private function store(): Repository
    {
        return $this->store ??= $this->cache->build([
            'driver' => 'file',
            'path' => storage_path('framework/cache/central-reference/'.$this->connectionIdentity()),
        ]);
    }

    private function connectionIdentity(): string
    {
        $connection = $this->connection();

        return hash('sha256', json_encode([
            $connection->getDriverName(),
            $connection->getConfig('host'),
            $connection->getConfig('port'),
            $connection->getDatabaseName(),
        ], JSON_THROW_ON_ERROR));
    }
}
