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
        $plan = Plan::where('code', 'free')
            ->orderByDesc('version')
            ->first();

        if ($plan === null) {
            throw new LogicException(
                'Free plan missing: run FreePlanSeeder first.'
            );
        }

        if (! $plan->is_active) {
            throw new LogicException(
                'The latest free plan version is inactive.'
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
            ->exists()) {
            throw new LogicException(
                'The latest free plan has no active shops quota.'
            );
        }

        return $plan;
    }
}
