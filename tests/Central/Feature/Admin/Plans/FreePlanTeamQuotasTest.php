<?php

use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\User;
use App\Services\Central\Plans\FreePlanCatalog;
use App\Services\Central\Plans\FreePlanTeamQuotaService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Database\Seeders\Central\TenantTeamQuotaFeatureSeeder;

// Aucune exécution automatique. Ce projet utilise MySQL temporaire isolé pour les tests.

test('free offer team quotas create an active version while preserving historical subscriptions and shop quota', function (): void {
    $this->seed(FreePlanSeeder::class);
    $this->seed(AuthorizationSeeder::class);
    $this->seed(TenantTeamQuotaFeatureSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));

    $current = app(FreePlanCatalog::class)->current();
    $owner = User::factory()->create();
    $previousSubscription = Subscription::factory()->create([
        'user_id' => $owner->id, 'plan_id' => $current->id,
    ]);
    $state = $current->compositionState($current->features()->orderBy('feature_id')->get());

    // Les valeurs sont des fixtures de test et ne sont jamais appliquées aux offres réelles.
    $draft = app(FreePlanTeamQuotaService::class)->update($actor, $current, [
        'expected_state' => $state,
        'expected_latest_version' => $current->version,
        'max_roles' => 4,
        'max_members' => 10,
    ]);

    expect($draft->version)->toBe($current->version + 1)
        ->and($draft->is_active)->toBeFalse()
        ->and($previousSubscription->fresh()->plan_id)->toBe($current->id)
        ->and(app(FreePlanCatalog::class)->current()->id)->toBe($current->id);

    $activated = app(FreePlanTeamQuotaService::class)->activate(
        $actor, $draft, $draft->compositionState($draft->features()->orderBy('feature_id')->get()),
    );
    expect($activated->is_active)->toBeTrue()
        ->and(app(FreePlanCatalog::class)->current()->id)->toBe($activated->id);

    $features = $activated->features()->with('feature')->get()->keyBy(fn (PlanFeature $row) => $row->feature->code);
    expect($features->get(Feature::SHOPS_CODE)->limit)->toBe(2)
        ->and($features->get(Feature::TEAM_CUSTOM_ROLES_CODE)->limit)->toBe(4)
        ->and($features->get(Feature::TEAM_MEMBERS_CODE)->limit)->toBe(10);
    expect($current->fresh()->is_active)->toBeFalse();
});
