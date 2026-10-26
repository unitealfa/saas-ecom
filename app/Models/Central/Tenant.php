<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\DTOs\Tenant\Shop\ShopSettings;
use App\Enums\Central\Tenants\StatusEnum;
use Database\Factories\Central\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property int $id
 * @property StatusEnum $status
 */
#[Fillable(['internal_label', 'shop_name', 'slug'])]
#[Hidden(['id', 'user_id', 'data', 'creation_key', 'creation_hash'])]
class Tenant extends BaseTenant implements TenantWithDatabase
{
    /** @use HasFactory<TenantFactory> */
    use HasDatabase, HasDomains, HasFactory, HasPublicUuid, SoftDeletes;

    public const array RESERVED_SLUGS = ['www', 'admin', 'api', 'app', 'mail', 'support'];

    public const string SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D';

    private const array IMMUTABLE_COLUMNS = ['user_id', 'document_prefix', 'creation_key', 'creation_hash'];

    protected $attributes = ['status' => 1, 'profile_version' => 1, 'is_primary' => false];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'profile_version' => 'integer',
            'status' => StatusEnum::class,
            'is_primary' => 'boolean',
            'activation_priority' => 'integer',
            'data' => 'array',
            'over_quota_since_at' => 'immutable_datetime',
            'provisioned_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tenant): void {
            $tenant->assertValidSlug();
            $tenant->reserveDatabaseName();
        });

        static::updating(function (self $tenant): void {
            if ($tenant->isDirty(self::IMMUTABLE_COLUMNS)) {
                throw new LogicException('Tenant ownership and provisioning identity are immutable.');
            }
        });
    }

    /** L'alias App\Models\Tenant configuré dans tenancy.tenant_model hérite de cette factory. */
    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    /** @return HasMany<Domain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'tenant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return list<string> */
    public static function getCustomColumns(): array
    {
        return [
            'id', 'uuid', 'user_id', 'internal_label', 'shop_name', 'slug', 'profile_version',
            'document_prefix', 'creation_key', 'creation_hash', 'status', 'is_primary',
            'activation_priority', 'over_quota_since_at', 'schema_version', 'provisioned_at',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public function getTenantKeyName(): string
    {
        return 'id';
    }

    /** @param array<string, mixed> $settings */
    public function configureShopForProvisioning(array $settings): static
    {
        if ($this->exists) {
            throw new LogicException('Initial shop settings must be provided before tenant creation.');
        }

        $this->setInternal('shop_settings', ShopSettings::fromArray($settings)->toArray());

        return $this;
    }

    private function assertValidSlug(): void
    {
        $slug = (string) $this->slug;

        if (! preg_match(self::SLUG_PATTERN, $slug) || in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new LogicException('Invalid or reserved shop slug.');
        }
    }

    private function reserveDatabaseName(): void
    {
        $databaseName = $this->database()->getName()
            ?? throw new LogicException('The tenant database name is missing.');

        if (static::withTrashed()->where('data->tenancy_db_name', $databaseName)->exists()
            || $this->database()->manager()->databaseExists($databaseName)) {
            throw new LogicException('The tenant database name is already reserved or exists.');
        }

        $this->setInternal('db_name', $databaseName);
    }
}
