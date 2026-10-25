<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;


#[Fillable(['code', 'name', 'value_type', 'unit', 'quota_scope', 'period', 'is_active'])]
class Feature extends Model
{
    use CentralConnection, HasFactory, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'value_type' => ValueTypeEnum::class,
            'quota_scope' => QuotaScopeEnum::class,
            'period' => PeriodEnum::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}