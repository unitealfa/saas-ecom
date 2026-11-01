<?php

namespace App\Models\Central\Shipping;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Geography\GeographicArea;
use App\Services\Central\Shipping\CarrierGeoMappingCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property int $carrier_id
 * @property int $geographic_area_id
 * @property GeoZoneTypeEnum $zone_type
 * @property string $external_code
 * @property string $external_name
 * @property string $external_province_code
 * @property string $verification_source
 * @property CarbonImmutable|null $verified_at
 * @property int $mapping_version
 * @property bool $is_active
 * @property CarbonImmutable|null $synced_at
 * @property-read ShippingCarrier $carrier
 * @property-read GeographicArea $area
 */
#[Fillable(['external_code', 'external_name', 'external_province_code', 'verification_source'])]
#[Hidden(['id', 'carrier_id', 'geographic_area_id'])]
class CarrierGeoMapping extends Model
{
    use CentralConnection, HasPublicUuid;

    public const string STATE_PATTERN = '/^[a-f0-9]{64}$/D';

    public const array EDITABLE = ['external_code', 'external_name', 'external_province_code', 'verification_source'];

    private const array STATE_FIELDS = [
        'uuid', 'carrier_id', 'geographic_area_id', 'zone_type', ...self::EDITABLE,
        'mapping_version', 'is_active', 'verified_at', 'synced_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $mapping): void {
            if ($mapping->isDirty(['carrier_id', 'geographic_area_id', 'zone_type'])) {
                throw new LogicException('A carrier mapping cannot change its carrier or geographic area.');
            }
        });
        $invalidate = static function (self $mapping): void {
            $mapping->getConnection()->afterCommit(fn () => app(CarrierGeoMappingCatalog::class)->invalidate());
        };
        static::saved($invalidate);
        static::deleted($invalidate);
    }

    protected function casts(): array
    {
        return ['zone_type' => GeoZoneTypeEnum::class, 'mapping_version' => 'integer',
            'is_active' => 'boolean', 'verified_at' => 'immutable_datetime', 'synced_at' => 'immutable_datetime'];
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

    public function managementState(): string
    {
        return hash('sha256', json_encode($this->only(self::STATE_FIELDS), JSON_THROW_ON_ERROR));
    }
}
