<?php

namespace App\Models\Central\Plans;

use App\Concerns\HasPublicUuid;
use Database\Factories\Central\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property string $monthly_price
 * @property string $annual_price
 */
#[Fillable(['code', 'version', 'name', 'description', 'monthly_price', 'annual_price', 'is_active'])]
#[UseFactory(PlanFactory::class)]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use CentralConnection, HasFactory, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'monthly_price' => 'decimal:2',
            'annual_price' => 'decimal:2',
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

    /** @return HasMany<PlanFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }
}
