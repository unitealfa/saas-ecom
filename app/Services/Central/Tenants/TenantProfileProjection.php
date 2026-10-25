<?php

namespace App\Services\Central\Tenants;

use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Shop;
use App\Models\Tenant\User as ShopUser;
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

        $tenant->run(function () use ($tenant, $owner): void {
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

        $tenant->run(function () use ($tenant): void {
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
            }, self::ATTEMPTS);
        });
    }

    private function databaseExists(Tenant $tenant): bool
    {
        $database = $tenant->database();

        return $database->manager()->databaseExists($database->getName());
    }
}
