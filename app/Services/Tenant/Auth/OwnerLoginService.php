<?php

namespace App\Services\Tenant\Auth;

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\ShopProfilePermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OwnerLoginService
{
    public function accept(string $ownerUuid, string $tenantUuid, string $emailHash): User
    {
        $tenant = tenancy()->tenant;
        abort_unless(tenancy()->initialized && $tenant instanceof Tenant && $tenant->uuid === $tenantUuid, 403);
        $tenant->refresh();
        $owner = $tenant->owner;
        abort_unless($owner !== null && $owner->uuid === $ownerUuid && hash_equals(hash('sha256', $owner->email), $emailHash), 403);
        Gate::forUser($owner)->authorize('accessOwn', $tenant);

        return DB::transaction(function () use ($owner): User {
            $local = User::where('central_user_uuid', $owner->uuid)->lockForUpdate()->firstOrFail();
            abort_unless($local->status === StatusEnum::ACTIVE
                && in_array($local->membership_status, [MembershipStatusEnum::INVITED, MembershipStatusEnum::ACTIVE], true)
                && $local->email === $owner->email, 403);
            if ($local->membership_status === MembershipStatusEnum::INVITED) {
                ShopProfilePermissions::seed();
                $local->forceFill(['membership_status' => MembershipStatusEnum::ACTIVE,
                    'joined_at' => now(), 'email_verified_at' => now()])->save();
                $local->givePermissionTo(array_keys(ShopProfilePermissions::ALL));
            }
            abort_unless($local->joined_at !== null && $local->email_verified_at !== null, 403);
            activity()->causedBy($local)->performedOn($local)->event('shop_owner_signed_in')->log('Connexion du proprietaire de la boutique.');

            return $local;
        }, 3);
    }
}
