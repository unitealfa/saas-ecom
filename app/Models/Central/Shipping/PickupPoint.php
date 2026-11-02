<?php

namespace App\Models\Central\Shipping;

use App\Concerns\HasPublicUuid;
use App\Models\Central\Geography\GeographicArea;
use App\Services\Central\Shipping\PickupPointCatalog;
use Carbon\CarbonImmutable;
use Database\Factories\Central\PickupPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property int $carrier_id
 * @property int $province_id
 * @property int|null $municipality_id
 * @property string $external_code
 * @property string $name
 * @property string $address
 * @property string|null $phone
 * @property string|null $map_url
 * @property bool $is_carrier_active
 * @property string $reference_source
 * @property int $reference_version
 * @property CarbonImmutable|null $synced_at
 * @property-read ShippingCarrier $carrier
 * @property-read GeographicArea $province
 * @property-read GeographicArea|null $municipality
 */
#[Fillable(['name', 'address', 'phone', 'map_url', 'reference_source'])]
#[Hidden(['id', 'carrier_id', 'province_id', 'municipality_id'])]
#[UseFactory(PickupPointFactory::class)]
class PickupPoint extends Model
{
    use CentralConnection, HasPublicUuid, SoftDeletes;

    /** @use HasFactory<PickupPointFactory> */
    use HasFactory;

    public const string STATE_PATTERN = '/^[a-f0-9]{64}$/D';

    public const array EDITABLE = ['name', 'address', 'phone', 'map_url', 'reference_source'];

    private const array IDENTITY = ['carrier_id', 'external_code', 'province_id', 'municipality_id'];

    public const array STATE_FIELDS = ['uuid', ...self::IDENTITY, ...self::EDITABLE, 'reference_version', 'is_carrier_active', 'synced_at', 'deleted_at'];

    protected static function booted(): void
    {
        static::updating(function (self $point): void {
            if ($point->isDirty(self::IDENTITY)) {
                throw new LogicException('A pickup point cannot change its carrier, code or geographic identity.');
            }
        });

        $invalidate = static function (self $point): void {
            $point->getConnection()->afterCommit(fn () => app(PickupPointCatalog::class)->invalidate());
        };
        static::saved($invalidate);
        static::deleted($invalidate);
        static::restored($invalidate);
    }

    protected function casts(): array
    {
        return ['is_carrier_active' => 'boolean', 'reference_version' => 'integer', 'synced_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ShippingCarrier, $this> */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class)->withTrashed();
    }

    /** @return BelongsTo<GeographicArea, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(GeographicArea::class, 'province_id')->withTrashed();
    }

    /** @return BelongsTo<GeographicArea, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(GeographicArea::class, 'municipality_id')->withTrashed();
    }

    public function managementState(): string
    {
        return hash('sha256', json_encode($this->only(self::STATE_FIELDS), JSON_THROW_ON_ERROR));
    }
}
