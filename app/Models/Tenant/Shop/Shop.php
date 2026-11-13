<?php

namespace App\Models\Tenant\Shop;

use App\Concerns\HasPublicUuid;
use App\Models\Tenant\Media\Media;
use Database\Factories\Tenant\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

#[Fillable([
    'business_type', 'theme_code', 'colors', 'cart_lifetime_days',
    'description', 'about', 'contact_email', 'contact_phone', 'contact_whatsapp',
    'locale', 'timezone',
])]
#[Hidden(['id', 'tenant_uuid', 'singleton'])]
#[UseFactory(ShopFactory::class)]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory, HasPublicUuid, TenantConnection;

    protected $table = 'shop';

    protected static function booted(): void
    {
        static::updating(function (self $shop): void {
            if ($shop->isDirty(['tenant_uuid', 'singleton'])) {
                throw new LogicException('Shop identity and singleton are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'singleton' => 'integer',
            'central_profile_version' => 'integer',
            'colors' => 'array',
            'shipping_tax_configuration' => 'array',
            'cart_lifetime_days' => 'integer',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function faviconMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'favicon_media_id');
    }

    /** @return HasMany<ShopAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(ShopAddress::class, 'shop_id');
    }

    /** @return HasMany<ShopSocialLink, $this> */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(ShopSocialLink::class, 'shop_id');
    }
}
