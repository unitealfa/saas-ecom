<?php

namespace Database\Factories\Central;

use App\Models\Central\Countries\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->countryCode(),
            'name_fr' => fake()->country(),
            'name_en' => fake()->country(),
            'is_active' => false,
        ];
    }
}
