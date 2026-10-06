<?php

use App\Models\Central\Domain;
use App\Models\Tenant;
use App\Tenancy\Bootstrappers\AccountContextBootstrapper;
use App\Tenancy\Bootstrappers\NamespacedCacheBootstrapper;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;

$appUrl = env('APP_URL', 'http://localhost');

if (! is_string($appUrl)) {
    throw new InvalidArgumentException('APP_URL must be a URL string.');
}

$centralDomain = parse_url($appUrl, PHP_URL_HOST);

if (! is_string($centralDomain) || $centralDomain === '') {
    throw new InvalidArgumentException('APP_URL must contain a valid application host.');
}

$centralDomain = strtolower($centralDomain);

return [
    'tenant_model' => Tenant::class,
    'domain_model' => Domain::class,
    'id_generator' => null,
    'central_domains' => [
        '127.0.0.1',
        'localhost',
        'aydra.localhost',
    ],
    'saas_base_domain' => env('SAAS_BASE_DOMAIN', $centralDomain),
    'bootstrappers' => [
        DatabaseTenancyBootstrapper::class,
        NamespacedCacheBootstrapper::class,
        FilesystemTenancyBootstrapper::class,
        QueueTenancyBootstrapper::class,
        AccountContextBootstrapper::class,
    ],
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),
        'template_tenant_connection' => null,
        'prefix' => 'boutique_',
        'suffix' => '',
        'managers' => [
            'sqlite' => SQLiteDatabaseManager::class,
            'mysql' => MySQLDatabaseManager::class,
            'mariadb' => MySQLDatabaseManager::class,
        ],
    ],
    'filesystem' => [
        'suffix_base' => 'tenant_',
        'disks' => ['local', 'public'],
        'root_override' => [
            'local' => '%storage_path%/app/private/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => false,
    ],
    'features' => [],
    'routes' => false,
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
        '--no-interaction' => true,
    ],
    'seeder_parameters' => [],
];
