<?php

namespace Database\Factories\Central;

use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'internal_label' => 'Test shop',
            'shop_name' => 'Test shop',
            'slug' => 'shop-'.Str::lower(Str::random(16)),
            'document_prefix' => Str::upper(Str::random(16)),
            'creation_key' => Str::uuid()->toString(),
            'creation_hash' => hash('sha256', Str::random(32)),
        ];
    }
}
