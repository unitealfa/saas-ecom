<?php

namespace App\Models\Central\Features;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Plans\PlanFeature;
use Database\Factories\Central\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property ValueTypeEnum $value_type
 * @property QuotaScopeEnum $quota_scope
 * @property PeriodEnum $period
 */
#[Fillable(['code', 'name', 'value_type', 'unit', 'quota_scope', 'period'])]
#[UseFactory(FeatureFactory::class)]
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use CentralConnection, HasFactory, HasPublicUuid, SoftDeletes;

    public const string SHOPS_CODE = 'shops';

    protected $attributes = ['is_active' => false];

    protected static function booted(): void
    {
        static::updating(function (self $feature): void {
            if ($feature->isDirty('code')) {
                throw new LogicException('A commercial feature code is immutable.');
            }
        });
    }

    /** @return Attribute<string, string> */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strtolower(trim($value)));
    }

    public function isSystem(): bool
    {
        return $this->code === self::SHOPS_CODE;
    }

    /** @return HasMany<PlanFeature, $this> */
    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function managementState(): string
    {
        return hash('sha256', json_encode($this->only([
            'uuid', 'code', 'name', 'value_type', 'unit', 'quota_scope', 'period', 'is_active', 'deleted_at',
        ]), JSON_THROW_ON_ERROR));
    }

    protected function casts(): array
    {
        return [
            'value_type' => ValueTypeEnum::class,
            'quota_scope' => QuotaScopeEnum::class,
            'period' => PeriodEnum::class,
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
