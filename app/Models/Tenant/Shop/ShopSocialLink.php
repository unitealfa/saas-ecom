<?php

namespace App\Models\Tenant\Shop;

use App\Concerns\HasPublicUuid;
use App\Enums\Tenant\Shop\ShopProfileRecordTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/** SOCIAL only, despite sharing the shop_addresses table. */
class ShopSocialLink extends Model
{
    use HasPublicUuid, SoftDeletes, TenantConnection;

    protected $table = 'shop_addresses';

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::addGlobalScope('social', fn (Builder $query) => $query->where('record_type', ShopProfileRecordTypeEnum::SOCIAL->value));
        static::creating(function (self $link): void {
            if ($link->record_type !== ShopProfileRecordTypeEnum::SOCIAL || $link->is_primary) {
                throw new LogicException('Invalid social link identity.');
            }
        });
        static::updating(function (self $link): void {
            if ($link->isDirty(['shop_id', 'record_type', 'uuid', 'is_primary'])) {
                throw new LogicException('Social link identity and type are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'is_primary' => 'boolean', 'visible' => 'boolean', 'position' => 'integer', 'record_type' => ShopProfileRecordTypeEnum::class];
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /** @return BelongsTo<ShopAddress, $this> */
    public function address(): BelongsTo
    {
        return $this->belongsTo(ShopAddress::class, 'shop_address_id');
    }
}
