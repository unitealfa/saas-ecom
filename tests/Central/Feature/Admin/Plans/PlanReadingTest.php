<?php

use App\Models\Central\Features\Feature;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\User;
use App\Services\Central\Plans\PlanCatalogService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\PlanPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

function planReader(string $permission = 'saas.plans.view'): User
{
    test()->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::createWithPermissions('plan-reader-'.Str::random(10), [$permission => 7]));

    return $user;
}

test('authorized staff can read all plan versions and their feature quotas', function (): void {
    $reader = planReader();
    $first = Plan::factory()->create(['code' => 'pro', 'version' => 1, 'name' => 'Pro']);
    $second = Plan::factory()->create(['code' => 'pro', 'version' => 2, 'name' => 'Pro', 'is_active' => false]);
    $second->delete();
    $feature = Feature::factory()->create(['code' => 'catalog.products', 'name' => 'Produits']);
    PlanFeature::factory()->create(['plan_id' => $first->id, 'feature_id' => $feature->id, 'limit' => 12]);

    $this->actingAs($reader, 'central')->getJson(route('central.plans.index', ['search' => 'pro']))
        ->assertOk()->assertJsonPath('data.0.uuid', $second->uuid)
        ->assertJsonPath('data.1.uuid', $first->uuid)->assertJsonMissingPath('data.0.id');

    $this->getJson(route('central.plans.show', $first->uuid))->assertOk()
        ->assertJsonPath('data.uuid', $first->uuid)
        ->assertJsonPath('data.features.0.code', 'catalog.products')
        ->assertJsonPath('data.features.0.limit', 12)
        ->assertJsonMissingPath('data.id')->assertJsonMissingPath('data.features.0.id');
    $this->getJson(route('central.plans.show', $second->uuid))->assertOk()
        ->assertJsonPath('data.version', 2);
    $this->getJson(route('central.plans.show', $first->id))->assertNotFound();
});

test('plan consultation requires its explicit central permission', function (): void {
    $reader = planReader('saas.features.view');
    $plan = Plan::factory()->create();

    $this->actingAs($reader, 'central')->getJson(route('central.plans.index'))->assertForbidden();
    $this->getJson(route('central.plans.show', $plan->uuid))->assertForbidden();
    expect(fn () => app(PlanCatalogService::class)->details($reader, $plan))
        ->toThrow(AuthorizationException::class);
});

test('plan list is paginated and requires authentication', function (): void {
    $this->getJson(route('central.plans.index'))->assertUnauthorized();
    $reader = planReader();
    Plan::factory()->count(21)->create();

    $this->actingAs($reader, 'central')->getJson(route('central.plans.index'))
        ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21)
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->getJson(route('central.plans.index', ['page' => 2]))
        ->assertOk()->assertJsonCount(1, 'data');
});

test('plan permission seeder preserves existing role assignments', function (): void {
    $reader = planReader('saas.features.view');
    $role = $reader->roles()->firstOrFail();
    $before = $role->permissions()->pluck('permissions.name')->all();

    $this->seed(PlanPermissionSeeder::class);
    $this->seed(PlanPermissionSeeder::class);

    expect(Permission::where('name', 'saas.plans.view')->where('guard_name', 'central')->count())->toBe(1);
    expect($role->permissions()->pluck('permissions.name')->all())->toBe($before);
});

test('plan search treats wildcard characters as literal text and validates inputs', function (): void {
    $reader = planReader();
    Plan::factory()->create(['code' => 'basic', 'name' => 'Basic']);
    $this->actingAs($reader, 'central')->getJson(route('central.plans.index', ['search' => '%']))
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('central.plans.index', ['search' => ['invalid']]))->assertUnprocessable();
    $this->getJson(route('central.plans.index', ['page' => 0]))->assertUnprocessable();
});
