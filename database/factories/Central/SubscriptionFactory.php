<?php

namespace Database\Factories\Central;

use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'plan_id' => Plan::factory(), 'operation_key' => (string) Str::uuid(), 'status' => 3, 'period' => 1, 'agreed_amount' => '0.00', 'started_at' => now(), 'period_starts_at' => now(), 'period_ends_at' => null, 'auto_renew' => false];
    }
}
