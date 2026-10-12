<?php

namespace App\Services\Central\Plans;

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Feature;
use App\Models\Central\Plan;
use App\Models\Central\PlanFeature;
use LogicException;

class FreePlanCatalog
{
    public function current(): Plan
    {
        if (tenancy()->initialized) {
            throw new LogicException('The free plan belongs to the central context.');
        }

        return (new Plan)->getConnection()->transaction(function (): Plan {
            $feature = Feature::firstOrCreate(['code' => 'shops'], [
                'name' => 'Nombre de boutiques', 'value_type' => ValueTypeEnum::QUANTITY,
                'quota_scope' => QuotaScopeEnum::OWNER, 'period' => PeriodEnum::LIFETIME, 'is_active' => true,
            ]);
            $feature = Feature::whereKey($feature->id)->lockForUpdate()->firstOrFail();
            if ($feature->value_type !== ValueTypeEnum::QUANTITY || $feature->quota_scope !== QuotaScopeEnum::OWNER
                || $feature->period !== PeriodEnum::LIFETIME || ! $feature->is_active) {
                throw new LogicException('The shops feature must be an active owner quota.');
            }

            $latest = Plan::where('code', 'free')->orderByDesc('version')->lockForUpdate()->first();
            if ($latest !== null && $latest->is_active && $latest->monthly_price === '0.00' && $latest->annual_price === '0.00'
                && $latest->features()->where('feature_id', $feature->id)->where('is_active', true)->where('limit', 2)->exists()) {
                return $latest;
            }

            $plan = Plan::create([
                'code' => 'free', 'version' => $latest === null ? 1 : $latest->version + 1,
                'name' => 'Gratuit', 'description' => 'Deux boutiques maximum par propriétaire.',
                'monthly_price' => '0.00', 'annual_price' => '0.00', 'is_active' => true,
            ]);
            PlanFeature::create(['plan_id' => $plan->id, 'feature_id' => $feature->id, 'is_active' => true, 'limit' => 2]);

            return $plan;
        }, 3);
    }
}
