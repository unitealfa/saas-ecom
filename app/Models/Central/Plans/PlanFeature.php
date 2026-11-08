<?php

namespace App\Models\Central\Plans;

use App\Concerns\HasPublicUuid;
use App\Models\Central\Features\Feature;
use App\Services\Central\Plans\PublicPlanCatalog;
use Database\Factories\Central\PlanFeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable(['plan_id', 'feature_id', 'is_active', 'limit'])]
#[UseFactory(PlanFeatureFactory::class)]
class PlanFeature extends Model
{
    /** @use HasFactory<PlanFeatureFactory> */
    use CentralConnection, HasFactory, HasPublicUuid;

    protected static function booted(): void
    {
        $invalidate = static function (self $feature): void {
            $feature->getConnection()->afterCommit(fn () => app(PublicPlanCatalog::class)->invalidate());
        };
        static::saved($invalidate);
        static::deleted($invalidate);
    }

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
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
