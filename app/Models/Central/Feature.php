<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use Database\Factories\Central\FeatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property ValueTypeEnum $value_type
 * @property QuotaScopeEnum $quota_scope
 * @property PeriodEnum $period
 */
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use CentralConnection, HasFactory, HasPublicUuid, SoftDeletes;

    protected $fillable = ['code', 'name', 'value_type', 'unit', 'quota_scope', 'period', 'is_active'];

    protected static function newFactory(): FeatureFactory
    {
        return FeatureFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value_type' => ValueTypeEnum::class,
            'quota_scope' => QuotaScopeEnum::class,
            'period' => PeriodEnum::class,
            'is_active' => 'boolean',
        ];
    }
}
