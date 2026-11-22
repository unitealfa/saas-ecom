<?php

use App\Models\Central\Audit\Activity;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Roles\Role;
use App\Models\User;
use App\Services\Central\Plans\PlanCreationService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function planCreationActor(string $permission): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $permissions = [$permission => 7];
    if ($permission === 'saas.plans.create') {
        $permissions['saas.plans.features.update'] = 7;
    }
    $actor->assignRole(Role::createWithPermissions('plan-creator-'.Str::random(10), $permissions));

    return $actor;
}

function planCreationPayload(): array
{
    $feature = Feature::factory()->create();

    return ['code' => ' PRO ', 'name' => ' Offre Pro ', 'description' => ' Une offre ', 'monthly_price' => '3000.00', 'annual_price' => '30000.00',
        'features' => [['uuid' => $feature->uuid, 'limit' => 3, 'unlimited' => false]]];
}

test('an authorized creator can create an inactive first version without consultation rights', function (): void {
    $actor = planCreationActor('saas.plans.create');
    $response = $this->actingAs($actor, 'central')->postJson(route('central.plans.store'), planCreationPayload())
        ->assertCreated()->assertJsonStructure(['data' => ['uuid']])->assertJsonMissingPath('data.id');
    $plan = Plan::where('uuid', $response->json('data.uuid'))->firstOrFail();

    expect($plan->code)->toBe('pro')->and($plan->name)->toBe('Offre Pro')
        ->and($plan->description)->toBe('Une offre')->and($plan->version)->toBe(1)
        ->and($plan->is_active)->toBeFalse()->and($plan->features()->count())->toBe(1);
    expect(Activity::forSubject($plan)->where('event', 'plan_created')->count())->toBe(1);
    $this->getJson(route('central.plans.show', $plan->uuid))->assertForbidden();
});

test('creation is denied without its own permission including direct service calls', function (): void {
    $actor = planCreationActor('saas.plans.view');
    $this->actingAs($actor, 'central')->postJson(route('central.plans.store'), planCreationPayload())->assertForbidden();
    expect(fn () => app(PlanCreationService::class)->create($actor, planCreationPayload()))->toThrow(AuthorizationException::class);
    expect(Plan::count())->toBe(0);
});

test('creation requires both the plan and feature permissions', function (): void {
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('plan-create-only', ['saas.plans.create' => 7]));
    $this->actingAs($actor, 'central')->postJson(route('central.plans.store'), planCreationPayload())->assertForbidden();
    expect(Plan::count())->toBe(0);
});

test('creation rejects duplicate and reserved codes including archived versions', function (): void {
    $actor = planCreationActor('saas.plans.create');
    $existing = Plan::factory()->create(['code' => 'pro', 'version' => 2]);
    $existing->delete();
    $this->actingAs($actor, 'central')->postJson(route('central.plans.store'), planCreationPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson(route('central.plans.store'), array_replace(planCreationPayload(), ['code' => 'free']))
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    expect(Plan::withTrashed()->count())->toBe(1);
});

test('direct service calls validate protected fields and monetary values', function (): void {
    $actor = planCreationActor('saas.plans.create');
    $service = app(PlanCreationService::class);

    expect(fn () => $service->create($actor, array_replace(planCreationPayload(), ['is_active' => true])))
        ->toThrow(ValidationException::class);
    expect(fn () => $service->create($actor, array_replace(planCreationPayload(), ['monthly_price' => '-1'])))
        ->toThrow(ValidationException::class);
    expect(Plan::count())->toBe(0);
});

test('creation rejects invalid prices and protected fields', function (array $changes, string $field): void {
    $actor = planCreationActor('saas.plans.create');
    $this->actingAs($actor, 'central')->postJson(route('central.plans.store'), array_replace(planCreationPayload(), $changes))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Plan::count())->toBe(0);
})->with([
    [['monthly_price' => '-1'], 'monthly_price'],
    [['annual_price' => '1.234'], 'annual_price'],
    [['description' => str_repeat('a', 65536)], 'description'],
    [['code' => ['invalid']], 'code'],
    [['is_active' => true], 'is_active'],
    [['version' => 2], 'version'],
    [['features' => []], 'features'],
]);

test('quantity requires a limit or an explicit unlimited choice', function (): void {
    $actor = planCreationActor('saas.plans.create');
    $data = planCreationPayload();
    $data['features'][0]['limit'] = null;
    $this->actingAs($actor, 'central')->postJson(route('central.plans.store'), $data)->assertUnprocessable();
    $data['features'][0]['unlimited'] = true;
    $this->postJson(route('central.plans.store'), $data)->assertCreated();
});

test('a zero price plan is created as an ordinary non-default version', function (): void {
    $actor = planCreationActor('saas.plans.create');
    $data = array_replace(planCreationPayload(), ['monthly_price' => '0.00', 'annual_price' => '0.00']);
    $uuid = $this->actingAs($actor, 'central')->postJson(route('central.plans.store'), $data)
        ->assertCreated()->json('data.uuid');
    $plan = Plan::where('uuid', $uuid)->sole();
    expect($plan->is_active)->toBeFalse()->and($plan->is_default)->toBeNull()
        ->and($plan->features()->count())->toBe(1);
});
