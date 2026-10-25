<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;


#[Fillable(['code', 'version', 'name', 'description', 'monthly_price', 'annual_price', 'is_active'])]
class Plan extends Model
{
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


    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }
}