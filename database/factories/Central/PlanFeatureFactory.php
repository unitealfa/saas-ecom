<?php

namespace Database\Factories\Central;

use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanFeature> */
class PlanFeatureFactory extends Factory
{
    protected $model = PlanFeature::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['plan_id' => Plan::factory(), 'feature_id' => Feature::factory(), 'is_active' => true, 'limit' => 2];
    }
}
