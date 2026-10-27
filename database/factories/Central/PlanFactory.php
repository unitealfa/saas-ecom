<?php

namespace Database\Factories\Central;

use App\Models\Central\Plans\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => 'test-'.fake()->unique()->word(), 'version' => 1, 'name' => fake()->word(), 'monthly_price' => '0.00', 'annual_price' => '0.00', 'is_active' => true];
    }
}
