<?php

namespace App\Models\Tenant;

use App\Concerns\HasPublicUuid;
use Database\Factories\Tenant\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

#[Fillable(['business_type', 'theme_code', 'colors', 'cart_lifetime_days'])]
#[Hidden(['id', 'tenant_uuid', 'singleton'])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory, HasPublicUuid, TenantConnection;

    protected $table = 'shop';

    protected static function newFactory(): ShopFactory
    {
        return ShopFactory::new();
    }

    /** @return array<string, string> */
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
}
