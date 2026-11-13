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

/** Address only. SOCIAL rows are never returned by this model. */
class ShopAddress extends Model
{
    use HasPublicUuid, SoftDeletes, TenantConnection;

    protected $table = 'shop_addresses';

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::addGlobalScope('address', fn (Builder $query) => $query->where('record_type', ShopProfileRecordTypeEnum::ADDRESS->value));
        static::creating(function (self $address): void {
            if ($address->record_type !== ShopProfileRecordTypeEnum::ADDRESS || $address->shop_address_id !== null) {
                throw new LogicException('Invalid address identity.');
            }
        });
        static::updating(function (self $address): void {
            if ($address->isDirty(['shop_id', 'record_type', 'uuid', 'shop_address_id'])) {
                throw new LogicException('Address identity and type are immutable.');
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
}
