<?php

namespace App\Services\Central\Tenants;

use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Users\User as ShopUser;
use App\Services\Tenant\Shop\ShopProfileCatalog;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class TenantProfileProjection
{
    private const int SINGLETON = 1;

    private const int ATTEMPTS = 3;

    public function assertPrepared(Tenant $tenant, User $owner): void
    {
        if (! filled($tenant->schema_version) || $tenant->provisioned_at === null || ! $this->databaseExists($tenant)) {
            throw ValidationException::withMessages(['tenant' => 'La base de cette boutique doit être préparée avant son activation.']);
        }

        $this->runInTenant($tenant, function () use ($tenant, $owner): void {
            $shop = Shop::where('singleton', self::SINGLETON)->first();

            if ($shop === null || $shop->tenant_uuid !== $tenant->uuid
                || ! ShopUser::where('central_user_uuid', $owner->uuid)->exists()) {
                throw ValidationException::withMessages(['tenant' => 'Le profil ou le compte propriétaire local n’est pas correctement initialisé.']);
            }
        });
    }

    public function sync(Tenant $tenant): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Tenant profile projections must start in the central context.');
        }

        $tenant = Tenant::whereKey($tenant->id)->where('uuid', $tenant->uuid)->firstOrFail();

        if (! filled($tenant->schema_version) || $tenant->provisioned_at === null || ! $this->databaseExists($tenant)) {
            throw new LogicException('The tenant database is not ready for profile projection.');
        }

        $this->runInTenant($tenant, function () use ($tenant): void {
            DB::transaction(function () use ($tenant): void {
                $shop = Shop::where('singleton', self::SINGLETON)->lockForUpdate()->firstOrFail();

                if ($shop->tenant_uuid !== $tenant->uuid) {
                    throw new LogicException('The shop profile belongs to another tenant.');
                }

                if ($shop->central_profile_version >= $tenant->profile_version) {
                    return;
                }

                $shop->forceFill([
                    'shop_name' => $tenant->shop_name,
                    'central_profile_version' => $tenant->profile_version,
                ])->save();
                // A central rename must not leave stale public profile cache entries.
                $shop->getConnection()->afterCommit(fn () => app(ShopProfileCatalog::class)->invalidate());
            }, self::ATTEMPTS);
        });
    }

    /** @param Closure(): void $callback */
    private function runInTenant(Tenant $tenant, Closure $callback): void
    {
        $previous = tenant();

        try {
            $tenant->run($callback);
        } finally {
            if ($previous !== null) {
                tenancy()->initialize($previous);
            } elseif (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    private function databaseExists(Tenant $tenant): bool
    {
        $database = $tenant->database();

        return $database->manager()->databaseExists($database->getName());
    }
}
