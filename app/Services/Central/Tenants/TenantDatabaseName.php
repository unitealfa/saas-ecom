<?php

namespace App\Services\Central\Tenants;

use App\Models\Central\Tenants\Tenant;
use LogicException;

class TenantDatabaseName
{
    /** Longueur maximale d'un identifiant de base MySQL/MariaDB. */
    private const int MAX_IDENTIFIER_LENGTH = 64;

    public function __invoke(Tenant $tenant): string
    {
        $slug = (string) $tenant->slug;

        if (! preg_match(Tenant::SLUG_PATTERN, $slug)) {
            throw new LogicException('A tenant database requires a valid shop slug.');
        }

        $prefix = (string) config('tenancy.database.prefix');
        $suffix = (string) config('tenancy.database.suffix');
        $availableLength = self::MAX_IDENTIFIER_LENGTH - strlen($prefix) - strlen($suffix);

        if ($availableLength < 1) {
            throw new LogicException('The tenant database prefix and suffix exceed the identifier limit.');
        }

        if (strlen($slug) > $availableLength) {
            throw new LogicException('The shop slug exceeds the tenant database identifier limit.');
        }

        return $prefix.$slug.$suffix;
    }
}
