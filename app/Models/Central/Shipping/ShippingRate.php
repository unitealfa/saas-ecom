<?php

namespace App\Models\Central\Shipping;

use App\Concerns\HasPublicUuid;
use App\Models\Central\Geography\GeographicArea;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Hidden(['id', 'carrier_id', 'geographic_area_id'])]
class ShippingRate extends Model
{
    use CentralConnection, HasPublicUuid;

    public const array SERVICES = ['delivery', 'pickup', 'exchange', 'collection', 'return'];

    public const array MODES = ['home', 'office'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<ShippingCarrier, $this> */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class)->withTrashed();
    }

    /** @return BelongsTo<GeographicArea, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(GeographicArea::class, 'geographic_area_id')->withTrashed();
    }
}
