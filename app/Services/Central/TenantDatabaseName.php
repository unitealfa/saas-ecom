<?php

namespace App\Services\Central;

use App\Models\Central\Tenant;
use LogicException;

class TenantDatabaseName
{
    public function __invoke(Tenant $tenant): string
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $tenant->slug)) {
            throw new LogicException('A tenant database requires a valid shop slug.');
        }

        $prefix = config('tenancy.database.prefix');
        $suffix = config('tenancy.database.suffix');
        $availableLength = 64 - strlen($prefix) - strlen($suffix);

        if ($availableLength < 1) {
            throw new LogicException('The tenant database prefix and suffix exceed the identifier limit.');
        }

        if (strlen($tenant->slug) > $availableLength) {
            throw new LogicException('The shop slug exceeds the tenant database identifier limit.');
        }

        return $prefix.$tenant->slug.$suffix;
    }
}
