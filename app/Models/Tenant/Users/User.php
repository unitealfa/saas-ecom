<?php

namespace App\Models\Tenant\Users;

use App\Concerns\HasProfilePhoto;
use App\Concerns\HasPublicUuid;
use App\Concerns\HasTimedPermissions;
use App\Concerns\LogsSafeActivity;
use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Tenant\Media\Media;
use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Roles\Role;
use Database\Factories\Tenant\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * @property StatusEnum $status
 * @property MembershipStatusEnum $membership_status
 */
#[Fillable(['last_name', 'first_name', 'email', 'phone', 'password', 'locale'])]
#[Hidden(['id', 'password', 'remember_token', 'central_user_uuid'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, Notifiable, SoftDeletes, TenantConnection;

    /** @use HasProfilePhoto<Media> */
    use HasProfilePhoto;

    /** @use HasTimedPermissions<Role, Permission> */
    use HasTimedPermissions;

    use LogsSafeActivity;

    protected string $guard_name = 'tenant';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => StatusEnum::class,
            'membership_status' => MembershipStatusEnum::class,
            'email_verified_at' => 'immutable_datetime',
            'joined_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            if ($user->isDirty('central_user_uuid')) {
                throw new LogicException('The local owner link cannot be changed.');
            }
        });
    }

    public function getMorphClass(): string
    {
        return 'shop_user';
    }
}
