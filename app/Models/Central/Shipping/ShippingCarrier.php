<?php

namespace App\Models\Central\Shipping;

use App\Concerns\HasPublicUuid;
use App\Services\Central\Shipping\ShippingCarrierCatalog;
use Carbon\CarbonImmutable;
use Database\Factories\Central\ShippingCarrierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property string $name
 * @property string $adapter
 * @property bool $is_active
 * @property int $reference_version
 * @property string $reference_source
 * @property string|null $default_api_url
 * @property CarbonImmutable|null $synced_at
 */
#[Fillable(['name'])]
#[Hidden(['id'])]
#[UseFactory(ShippingCarrierFactory::class)]
class ShippingCarrier extends Model
{
    /** @use HasFactory<ShippingCarrierFactory> */
    use CentralConnection, HasFactory, HasPublicUuid, SoftDeletes;

    public const string YALIDINE = 'yalidine';

    public const string YALIDINE_SOURCE = 'https://www.yalidine.com/';

    public const string UNCONFIGURED_ADAPTER = 'unconfigured';

    public const string MANUAL_SOURCE = 'administration';

    public const string CODE_PATTERN = '/^[a-z][a-z0-9_-]*$/D';

    /** Format du jeton `expected_state` (empreinte SHA-256 en hexadécimal). */
    public const string STATE_PATTERN = '/^[a-f0-9]{64}$/D';

    private const array STATE_FIELDS = [
        'uuid', 'code', 'name', 'adapter', 'default_api_url', 'is_active',
        'reference_source', 'reference_version', 'synced_at', 'deleted_at',
    ];

    protected $attributes = ['is_active' => false, 'reference_version' => 1];

    protected static function booted(): void
    {
        static::updating(function (self $carrier): void {
            if ($carrier->isDirty(['code', 'adapter'])) {
                throw new LogicException('Carrier code and adapter are immutable.');
            }
        });

        // Le catalogue en cache n'est invalidé qu'une fois la transaction validée.
        $invalidate = static function (self $carrier): void {
            $carrier->getConnection()->afterCommit(fn () => app(ShippingCarrierCatalog::class)->invalidate());
        };

        static::saved($invalidate);
        static::deleted($invalidate);
        static::restored($invalidate);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'reference_version' => 'integer',
            'synced_at' => 'immutable_datetime',
        ];
    }

    /** Empreinte de l'état courant, utilisée pour détecter les modifications concurrentes. */
    public function managementState(): string
    {
        return hash('sha256', json_encode($this->only(self::STATE_FIELDS), JSON_THROW_ON_ERROR));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
