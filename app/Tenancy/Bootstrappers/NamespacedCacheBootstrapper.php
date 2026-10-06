<?php

namespace App\Tenancy\Bootstrappers;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class NamespacedCacheBootstrapper implements TenancyBootstrapper
{
    /** @var array<string, mixed> */
    private array $original = [];

    public function __construct(private Repository $config, private CacheManager $cache) {}

    public function bootstrap(Tenant $tenant): void
    {
        foreach (['cache.prefix', 'cache.stores.database.connection', 'cache.stores.database.lock_connection', 'cache.stores.file.path', 'cache.stores.file.lock_path'] as $key) {
            $this->original[$key] = $this->config->get($key);
        }

        $this->config->set([
            'cache.prefix' => 'tenants:'.$tenant->getTenantKey().':',
            'cache.stores.database.connection' => $this->config->get('tenancy.database.central_connection'),
            'cache.stores.database.lock_connection' => $this->config->get('tenancy.database.central_connection'),
            'cache.stores.file.path' => storage_path('framework/cache/tenants/'.$tenant->getTenantKey()),
            'cache.stores.file.lock_path' => storage_path('framework/cache/tenants/'.$tenant->getTenantKey()),
        ]);
        $this->cache->forgetDriver();
    }

    public function revert(): void
    {
        $this->config->set($this->original);
        $this->cache->forgetDriver();
        $this->original = [];
    }
}
