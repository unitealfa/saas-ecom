<?php

namespace App\Jobs\Central;

use App\Models\Central\Tenants\Tenant;
use App\Services\Central\Tenants\TenantProfileProjection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncTenantProfile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $tenantId, public readonly string $tenantUuid)
    {
        $this->onConnection('central-provisioning');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(TenantProfileProjection $profiles): void
    {
        $tenant = Tenant::whereKey($this->tenantId)->where('uuid', $this->tenantUuid)->first();
        if ($tenant !== null) {
            $profiles->sync($tenant);
        }
    }
}
