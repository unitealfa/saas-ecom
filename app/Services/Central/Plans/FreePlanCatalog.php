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
    public function current(): Plan
    {
        if (tenancy()->initialized) {
            throw new LogicException(
                'The free plan belongs to the central context.'
            );
        }

        // Lire la dernière version du plan gratuit.
        $plan = Plan::withTrashed()->where('code', 'free')
            ->orderByDesc('version')
            ->first();

        if ($plan === null) {
            throw new LogicException(
                'Free plan missing: run FreePlanSeeder first.'
            );
        }

        if ($plan->trashed() || ! $plan->is_active || $plan->monthly_price !== '0.00' || $plan->annual_price !== '0.00') {
            throw new LogicException(
                'The latest free plan must be non-archived, active and free of charge.'
            );
        }

        // Vérifier que la fonctionnalité boutiques est valide.
        $feature = Feature::where(
            'code',
            Feature::SHOPS_CODE
        )->first();

        if (
            $feature === null
            || ! $feature->is_active
            || $feature->value_type !== ValueTypeEnum::QUANTITY
            || $feature->quota_scope !== QuotaScopeEnum::OWNER
            || $feature->period !== PeriodEnum::LIFETIME
        ) {
            throw new LogicException(
                'The shops feature must be an active owner quota.'
            );
        }

        // Le quota doit être associé au plan.
        if (! PlanFeature::where('plan_id', $plan->id)
            ->where('feature_id', $feature->id)
            ->where('is_active', true)
            ->where('limit', 2)
            ->exists()) {
            throw new LogicException(
                'The latest free plan has no active shops quota.'
            );
        }

        return $plan;
    }
}
