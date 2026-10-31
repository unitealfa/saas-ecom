<?php

namespace App\Models\Central\Geography;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Countries\Country;
use App\Services\Central\Geography\GeographyCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property GeoZoneTypeEnum $type
 * @property Carbon|null $effective_at
 * @property int $country_id
 * @property int|null $parent_id
 * @property bool $is_active
 * @property-read Country $country
 * @property-read GeographicArea|null $parent
 */
#[Fillable(['code', 'name_fr', 'name_ar', 'reference_source', 'reference_version', 'effective_at'])]
#[Hidden(['id', 'country_id', 'parent_id', 'parent_type', 'parent_key'])]
class GeographicArea extends Model
{
    use CentralConnection, HasPublicUuid, SoftDeletes;

    /** Seul ce pays alimente les zones sélectionnables. */
    private const string SELECTABLE_COUNTRY = 'DZ';

    private const array IMMUTABLE = ['id', 'country_id', 'type', 'parent_id', 'code'];

    protected static function booted(): void
    {
        static::updating(function (self $area): void {
            if ($area->isDirty(self::IMMUTABLE)) {
                throw new LogicException('Geographic identity and parent cannot change.');
            }
        });

        // Le catalogue en cache n'est invalidé qu'une fois la transaction validée.
        $invalidate = static function (self $area): void {
            $area->getConnection()->afterCommit(fn () => app(GeographyCatalog::class)->invalidate());
        };

        static::saved($invalidate);
        static::deleted($invalidate);
        static::restored($invalidate);
    }

    protected function casts(): array
    {
        return [
            'type' => GeoZoneTypeEnum::class,
            'is_active' => 'boolean',
            'effective_at' => 'date',
        ];
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class)->withTrashed();
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Zones proposées à la sélection : actives, rattachées à un pays actif, et
     * pour une commune, dont la wilaya parente est elle-même active et non archivée.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->active()
            ->whereHas('country', fn (Builder $country) => $country
                ->whereNull('deleted_at')
                ->where('code', self::SELECTABLE_COUNTRY)
                ->where('is_active', true))
            ->where(fn (Builder $zones) => $zones
                ->where('type', GeoZoneTypeEnum::PROVINCE)
                ->orWhereHas('parent', fn (Builder $parent) => $parent->active()->whereNull('deleted_at')));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $pattern = '%'.addcslashes($search, '\\%_').'%';

        return $query->where(fn (Builder $zones) => $zones
            ->where('name_fr', 'like', $pattern)
            ->orWhere('name_ar', 'like', $pattern)
            ->orWhere('code', 'like', $pattern));
    }

    /** Empreinte de l'état courant, utilisée pour détecter les modifications concurrentes. */
    public function stateToken(): string
    {
        return hash('sha256', json_encode($this->getRawOriginal(), JSON_THROW_ON_ERROR));
    }
}