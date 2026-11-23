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
use App\Services\Tenant\Authorization\TenantAuthorizationMode;
use Database\Factories\Tenant\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use LogicException;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * @property StatusEnum $status
 * @property MembershipStatusEnum $membership_status
 */
#[Fillable(['last_name', 'first_name', 'email', 'phone', 'password', 'locale'])]
#[Hidden(['id', 'password', 'remember_token', 'central_user_uuid', 'two_factor_secret', 'two_factor_recovery_codes'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, Notifiable, SoftDeletes, TenantConnection, TwoFactorAuthenticatable;

    /** @use HasProfilePhoto<Media> */
    use HasProfilePhoto;

    /** @use HasTimedPermissions<Role, Permission> */
    use HasTimedPermissions {
        hasPermissionTo as private legacyHasPermissionTo;
        getPermissionsViaRoles as private legacyPermissionsViaRoles;
        hasPermissionViaRole as private legacyHasPermissionViaRole;
    }

    use LogsSafeActivity;
    use PasskeyAuthenticatable;

    public function passkeys(): HasMany
    {
        return $this->hasMany(Passkey::class);
    }

    public function getPasskeyUserHandle(): string
    {
        return hash_hmac('sha256', (string) tenant()->getTenantKey().'|'.$this->uuid,
            config('passkeys.user_handle_secret'), true);
    }

    protected string $guard_name = 'tenant';

    /** Only direct, unexpired, active grants authorize actions after this shop was converted. */
    public function hasPermissionTo(mixed $permission, ?string $guardName = null): bool
    {
        if (! TenantAuthorizationMode::individualOnly()) {
            return $this->legacyHasPermissionTo($permission, $guardName);
        }
        if ($this->status !== StatusEnum::ACTIVE
            || $this->membership_status !== MembershipStatusEnum::ACTIVE
            || $this->trashed() || $this->joined_at === null || $this->email_verified_at === null) {
            return false;
        }
        $item = $this->filterPermission($permission, $guardName);
        $now = now();

        return $this->permissions()->whereKey($item->getKey())
            ->wherePivot('assigned_at', '<=', $now)
            ->wherePivot('expires_at', '>', $now)->exists();
    }

    /** Spatie's checkPermissionTo/can delegate to the overridden hasPermissionTo. */
    public function getPermissionsViaRoles(): Collection
    {
        return TenantAuthorizationMode::individualOnly() ? collect() : $this->legacyPermissionsViaRoles();
    }

    protected function hasPermissionViaRole(\Spatie\Permission\Contracts\Permission $permission): bool
    {
        return ! TenantAuthorizationMode::individualOnly()
            && $this->legacyHasPermissionViaRole($permission);
    }

    public function getDirectPermissions(): Collection
    {
        if (! TenantAuthorizationMode::individualOnly()) {
            return $this->permissions;
        }
        $now = now();

        return $this->permissions()->wherePivot('assigned_at', '<=', $now)
            ->wherePivot('expires_at', '>', $now)->get();
    }

    public function getAllPermissions(): Collection
    {
        return TenantAuthorizationMode::individualOnly()
            ? $this->getDirectPermissions() : $this->getDirectPermissions()->merge($this->getPermissionsViaRoles())->unique('id');
    }

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
