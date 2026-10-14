<?php

namespace App\Services\Central\Tenants;

use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Shop;
use App\Models\Tenant\User as ShopUser;
use Illuminate\Validation\ValidationException;
use LogicException;

class TenantProfileProjection
{
    public function assertPrepared(Tenant $tenant, User $owner): void
    {
        if ($tenant->schema_version === null || $tenant->provisioned_at === null
            || ! $tenant->database()->manager()->databaseExists($tenant->database()->getName())) {
            throw ValidationException::withMessages(['tenant' => 'La base de cette boutique doit être préparée avant son activation.']);
        }
        $tenant->run(function () use ($tenant, $owner): void {
            $shop = Shop::where('singleton', 1)->first();
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
        if ($tenant->schema_version === null || ! $tenant->database()->manager()->databaseExists($tenant->database()->getName())) {
            throw new LogicException('The tenant database is not ready for profile projection.');
        }
        $tenant->run(function () use ($tenant): void {
            (new Shop)->getConnection()->transaction(function () use ($tenant): void {
                $shop = Shop::where('singleton', 1)->lockForUpdate()->firstOrFail();
                if ($shop->tenant_uuid !== $tenant->uuid) {
                    throw new LogicException('The shop profile belongs to another tenant.');
                }
                if ($shop->central_profile_version >= $tenant->profile_version) {
                    return;
                }
                $shop->forceFill(['shop_name' => $tenant->shop_name, 'central_profile_version' => $tenant->profile_version])->save();
            }, 3);
        });
    }
}
