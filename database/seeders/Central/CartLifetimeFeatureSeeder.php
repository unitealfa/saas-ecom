<?php

namespace Database\Seeders\Central;

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use Illuminate\Database\Seeder;
use LogicException;

class CartLifetimeFeatureSeeder extends Seeder
{
    public function run(): void
    {
        abort_if(tenancy()->initialized, 403);
        $feature = Feature::withTrashed()->firstOrCreate(['code' => Feature::CART_LIFETIME_CODE], [
            'name' => 'Durée du panier par boutique', 'unit' => 'jours',
            'value_type' => ValueTypeEnum::QUANTITY, 'quota_scope' => QuotaScopeEnum::TENANT,
            'period' => PeriodEnum::LIFETIME,
        ]);
        if ($feature->wasRecentlyCreated) {
            $feature->forceFill(['is_active' => true])->save();
        }
        if ($feature->trashed() || ! $feature->is_active || $feature->value_type !== ValueTypeEnum::QUANTITY
            || $feature->quota_scope !== QuotaScopeEnum::TENANT || $feature->period !== PeriodEnum::LIFETIME) {
            throw new LogicException('La fonctionnalité de durée du panier existe avec une configuration incompatible.');
        }
    }
}
