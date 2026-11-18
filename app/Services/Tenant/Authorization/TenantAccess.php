<?php

namespace App\Services\Tenant\Authorization;

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Users\User;

final class TenantAccess
{
    public static function active(User $user): bool
    {
        return tenancy()->initialized && $user->status === StatusEnum::ACTIVE
            && $user->membership_status === MembershipStatusEnum::ACTIVE
            && $user->joined_at !== null && $user->email_verified_at !== null
            && ! $user->trashed();
    }

    public static function allows(User $user, string $permission): bool
    {
        return self::active($user) && TenantAuthorizationMode::individualOnly()
            && $user->can($permission);
    }

    public static function isOwner(User $user): bool
    {
        $tenant = tenancy()->tenant;
        return tenancy()->initialized && $tenant instanceof Tenant
            && $tenant->owner !== null
            && $user->central_user_uuid !== null
            && $tenant->owner->uuid === $user->central_user_uuid;
    }

    public static function editableEmployee(User $target): bool
    {
        // Any central owner link is reserved until explicitly verified; never mutate it here.
        return $target->central_user_uuid === null && ! self::isOwner($target);
    }
}
