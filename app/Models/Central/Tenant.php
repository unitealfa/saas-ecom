<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use Database\Factories\Central\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $shop_name
 * @property string $slug
 * @property string $creation_hash
 * @property TenantStatus $status
 * @property-read User $owner
 */
#[Fillable(['internal_label', 'shop_name', 'slug'])]
#[Hidden(['id', 'user_id', 'data', 'creation_key', 'creation_hash'])]
class Tenant extends BaseTenant implements TenantWithDatabase
{
    protected $attributes = ['status' => 1, 'profile_version' => 1, 'is_primary' => false];

    /** @use HasFactory<TenantFactory> */
    use HasDatabase, HasDomains, HasFactory, HasPublicUuid, SoftDeletes;

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    /** @return list<string> */
    public static function getCustomColumns(): array
    {
        return ['id', 'uuid', 'user_id', 'internal_label', 'shop_name', 'slug', 'profile_version', 'document_prefix', 'creation_key', 'creation_hash', 'status', 'is_primary', 'activation_priority', 'over_quota_since_at', 'schema_version', 'provisioned_at', 'created_at', 'updated_at', 'deleted_at'];
    }

    public function getTenantKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'is_primary' => 'boolean',
            'over_quota_since_at' => 'immutable_datetime',
            'provisioned_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tenant): void {
            $reserved = ['www', 'admin', 'api', 'app', 'mail', 'support'];

            if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $tenant->slug) || in_array($tenant->slug, $reserved, true)) {
                throw new LogicException('Invalid or reserved shop slug.');
            }
        });

        static::updating(function (self $tenant): void {
            if ($tenant->isDirty(['user_id', 'document_prefix', 'creation_key', 'creation_hash'])) {
                throw new LogicException('Tenant ownership and provisioning identity are immutable.');
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
