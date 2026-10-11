<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use Database\Factories\Central\PlanFeatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 */
class PlanFeature extends Model
{
    /** @use HasFactory<PlanFeatureFactory> */
    use CentralConnection, HasFactory, HasPublicUuid;

    protected $fillable = ['plan_id', 'feature_id', 'is_active', 'limit'];

    protected static function newFactory(): PlanFeatureFactory
    {
        return PlanFeatureFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'plan_id' => 'integer',
            'feature_id' => 'integer',
            'is_active' => 'boolean',
            'limit' => 'integer',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }
}
