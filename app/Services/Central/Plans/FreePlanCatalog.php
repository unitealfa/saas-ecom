<?php

namespace App\Services\Central\Plans;

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use LogicException;

class FreePlanCatalog
{
    private const string PLAN_CODE = 'free';

    private const string ZERO_PRICE = '0.00';

    private const int SHOP_LIMIT = 2;

    public function current(): Plan
    {
        if (tenancy()->initialized) {
            throw new LogicException('The free plan belongs to the central context.');
        }

        $plan = Plan::withTrashed()->where('code', self::PLAN_CODE)->orderByDesc('version')->first()
            ?? throw new LogicException('Free plan missing: run FreePlanSeeder first.');

        if ($plan->trashed()
            || ! $plan->is_active
            || $plan->monthly_price !== self::ZERO_PRICE
            || $plan->annual_price !== self::ZERO_PRICE) {
            throw new LogicException('The latest free plan must be non-archived, active and free of charge.');
        }

        $feature = Feature::where('code', Feature::SHOPS_CODE)->first();

        if ($feature === null || ! $this->isOwnerQuota($feature)) {
            throw new LogicException('The shops feature must be an active owner quota.');
        }

        $hasQuota = PlanFeature::where('plan_id', $plan->id)
            ->where('feature_id', $feature->id)
            ->where('is_active', true)
            ->where('limit', self::SHOP_LIMIT)
            ->exists();

        if (! $hasQuota) {
            throw new LogicException('The latest free plan has no active shops quota.');
        }

        return $plan;
    }

    private function isOwnerQuota(Feature $feature): bool
    {
        return $feature->is_active
            && $feature->value_type === ValueTypeEnum::QUANTITY
            && $feature->quota_scope === QuotaScopeEnum::OWNER
            && $feature->period === PeriodEnum::LIFETIME;
    }
}