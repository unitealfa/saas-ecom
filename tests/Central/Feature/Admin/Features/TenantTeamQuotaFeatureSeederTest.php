<?php

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Permissions\Permission;
use Database\Seeders\Central\TenantTeamQuotaFeatureSeeder;

// Tests proposés. Ne pas exécuter sans accord explicite : les tests de ce projet créent des BDD MySQL isolées.

test('team quota seeder is additive and does not choose limits for any offer', function (): void {
    $beforePermissions = Permission::count();
    $this->seed(TenantTeamQuotaFeatureSeeder::class);
    $this->seed(TenantTeamQuotaFeatureSeeder::class);

    foreach ([Feature::TEAM_CUSTOM_ROLES_CODE, Feature::TEAM_MEMBERS_CODE] as $code) {
        $feature = Feature::where('code', $code)->sole();
        expect($feature->value_type)->toBe(ValueTypeEnum::QUANTITY)
            ->and($feature->quota_scope)->toBe(QuotaScopeEnum::TENANT)
            ->and($feature->period)->toBe(PeriodEnum::LIFETIME)
            ->and($feature->is_active)->toBeTrue()
            ->and($feature->isSystem())->toBeTrue()
            ->and(PlanFeature::where('feature_id', $feature->id)->count())->toBe(0);
    }
    expect(Feature::whereIn('code', [Feature::TEAM_CUSTOM_ROLES_CODE, Feature::TEAM_MEMBERS_CODE])->count())->toBe(2)
        ->and(Permission::count())->toBe($beforePermissions);
});

test('team quota seeder refuses to reinterpret an existing feature', function (): void {
    Feature::factory()->create([
        'code' => Feature::TEAM_MEMBERS_CODE,
        'quota_scope' => QuotaScopeEnum::OWNER,
    ]);

    expect(fn () => $this->seed(TenantTeamQuotaFeatureSeeder::class))
        ->toThrow(LogicException::class);
    expect(Feature::where('code', Feature::TEAM_MEMBERS_CODE)->firstOrFail()->quota_scope)
        ->toBe(QuotaScopeEnum::OWNER);
});
