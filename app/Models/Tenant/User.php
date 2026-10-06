<?php

namespace App\Models\Tenant;

use App\Concerns\HasPublicUuid;
use Database\Factories\Tenant\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $central_user_uuid
 * @property string $password
 * @property int $membership_status
 */
#[Fillable(['last_name', 'first_name', 'email', 'phone', 'password', 'locale'])]
#[Hidden(['id', 'password', 'remember_token', 'central_user_uuid'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, Notifiable, SoftDeletes, TenantConnection;

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            if ($user->isDirty('central_user_uuid')) {
                throw new LogicException('The local owner link cannot be changed.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'immutable_datetime',
            'joined_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
