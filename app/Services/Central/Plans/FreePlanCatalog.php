<?php

namespace App\Services\Central\Plans;

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use Illuminate\Support\Facades\DB;
use LogicException;

class FreePlanCatalog
{
    private const string PLAN_CODE = 'free';

    private const string ZERO_PRICE = '0.00';

    private const int SHOP_LIMIT = 2;

    private const int ATTEMPTS = 3;

    public function current(): Plan
    {
        if (tenancy()->initialized) {
            throw new LogicException('The free plan belongs to the central context.');
        }

        return DB::transaction(function (): Plan {
            $feature = $this->shopsFeature();
            $latest = Plan::where('code', self::PLAN_CODE)->orderByDesc('version')->lockForUpdate()->first();

            if ($latest !== null && $this->isCurrent($latest, $feature)) {
                return $latest;
            }

            return $this->createVersion($feature, ($latest->version ?? 0) + 1);
        }, self::ATTEMPTS);
    }

    /** Retourne la fonctionnalité « boutiques » verrouillée, en la créant active si elle n'existe pas. */
    private function shopsFeature(): Feature
    {
        $created = Feature::firstOrCreate(['code' => Feature::SHOPS_CODE], [
            'name' => 'Nombre de boutiques',
            'value_type' => ValueTypeEnum::QUANTITY,
            'quota_scope' => QuotaScopeEnum::OWNER,
            'period' => PeriodEnum::LIFETIME,
        ]);

        if ($created->wasRecentlyCreated) {
            $created->forceFill(['is_active' => true])->save();
        }

        $feature = Feature::whereKey($created->id)->lockForUpdate()->firstOrFail();

        if (! $this->isOwnerQuota($feature)) {
            throw new LogicException('The shops feature must be an active owner quota.');
        }

        return $feature;
    }

    private function isOwnerQuota(Feature $feature): bool
    {
        return $feature->value_type === ValueTypeEnum::QUANTITY
            && $feature->quota_scope === QuotaScopeEnum::OWNER
            && $feature->period === PeriodEnum::LIFETIME
            && $feature->is_active;
    }

    private function isCurrent(Plan $plan, Feature $feature): bool
    {
        return $plan->is_active
            && $plan->monthly_price === self::ZERO_PRICE
            && $plan->annual_price === self::ZERO_PRICE
            && $plan->features()
                ->where('feature_id', $feature->id)
                ->where('is_active', true)
                ->where('limit', self::SHOP_LIMIT)
                ->exists();
    }

    private function createVersion(Feature $feature, int $version): Plan
    {
        $plan = Plan::create([
            'code' => self::PLAN_CODE,
            'version' => $version,
            'name' => 'Gratuit',
            'description' => 'Deux boutiques maximum par propriétaire.',
            'monthly_price' => self::ZERO_PRICE,
            'annual_price' => self::ZERO_PRICE,
            'is_active' => true,
        ]);

        PlanFeature::create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
            'is_active' => true,
            'limit' => self::SHOP_LIMIT,
        ]);

        return $plan;
    }
}
