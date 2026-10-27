<?php

namespace Database\Factories\Central;

use App\Models\Central\Features\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Feature> */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => 'test.'.fake()->unique()->word(), 'name' => fake()->word(), 'value_type' => 2, 'quota_scope' => 1, 'period' => 1, 'is_active' => true];
    }
}
