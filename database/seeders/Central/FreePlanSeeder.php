<?php

namespace Database\Seeders\Central;

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Services\Central\Plans\FreePlanCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class FreePlanSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('The free plan belongs to the central context.');
        }

        DB::transaction(function (): void {
            // Ne pas recréer ni modifier un plan déjà existant.
            $existing = Plan::withTrashed()->where('code', 'free')
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                app(FreePlanCatalog::class)->current();

                return;
            }

            // Fonctionnalité : nombre de boutiques par propriétaire.
            $feature = Feature::withTrashed()->firstOrCreate(
                ['code' => Feature::SHOPS_CODE],
                [
                    'name' => 'Nombre de boutiques',
                    'value_type' => ValueTypeEnum::QUANTITY,
                    'quota_scope' => QuotaScopeEnum::OWNER,
                    'period' => PeriodEnum::LIFETIME,
                ]
            );

            if ($feature->wasRecentlyCreated) {
                $feature->forceFill(['is_active' => true])->save();
            }

            if (
                $feature->trashed()
                || $feature->value_type !== ValueTypeEnum::QUANTITY
                || $feature->quota_scope !== QuotaScopeEnum::OWNER
                || $feature->period !== PeriodEnum::LIFETIME
                || ! $feature->is_active
            ) {
                throw new LogicException(
                    'The shops feature must be an active owner quota.'
                );
            }

            // Valeurs initiales du plan gratuit.
            $plan = Plan::create([
                'code' => 'free',
                'version' => 1,
                'name' => 'Gratuit',
                'description' => 'Deux boutiques maximum par propriétaire.',
                'monthly_price' => '0.00',
                'annual_price' => '0.00',
                'is_active' => true,
            ]);

            // Quota initial : 2 boutiques.
            PlanFeature::create([
                'plan_id' => $plan->id,
                'feature_id' => $feature->id,
                'is_active' => true,
                'limit' => 2,
            ]);
        }, 3);
    }
}
