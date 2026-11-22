<?php

namespace Database\Seeders\Central;

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use Illuminate\Database\Seeder;
use LogicException;

class DomainQuotaFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        abort_if(tenancy()->initialized, 403);
        $feature = Feature::withTrashed()->firstOrCreate(['code' => Feature::DOMAINS_CODE], [
            'name' => 'Domaines supplémentaires par boutique', 'unit' => 'domaines',
            'value_type' => ValueTypeEnum::QUANTITY, 'quota_scope' => QuotaScopeEnum::TENANT, 'period' => PeriodEnum::LIFETIME,
        ]);
        if ($feature->wasRecentlyCreated) {
            $feature->forceFill(['is_active' => true])->save();
        }
        if ($feature->trashed() || ! $feature->is_active || $feature->value_type !== ValueTypeEnum::QUANTITY
            || $feature->quota_scope !== QuotaScopeEnum::TENANT || $feature->period !== PeriodEnum::LIFETIME) {
            throw new LogicException('Configuration incompatible du quota de domaines.');
        }
    }
}
