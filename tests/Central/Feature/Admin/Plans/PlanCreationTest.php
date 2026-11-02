<?php

use App\Models\Central\Audit\Activity;
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
    $actor->assignRole(Role::createWithPermissions('plan-creator-'.Str::random(10), [$permission => 7]));

    return $actor;
}

function planCreationPayload(): array
{
    return ['code' => ' PRO ', 'name' => ' Offre Pro ', 'description' => ' Une offre ', 'monthly_price' => '3000.00', 'annual_price' => '30000.00'];
}

test('an authorized creator can create an inactive first version without consultation rights', function (): void {
    $actor = planCreationActor('saas.plans.create');
    $response = $this->actingAs($actor, 'central')->postJson(route('central.plans.store'), planCreationPayload())
        ->assertCreated()->assertJsonStructure(['data' => ['uuid']])->assertJsonMissingPath('data.id');
    $plan = Plan::where('uuid', $response->json('data.uuid'))->firstOrFail();

    expect($plan->code)->toBe('pro')->and($plan->name)->toBe('Offre Pro')
        ->and($plan->description)->toBe('Une offre')->and($plan->version)->toBe(1)
        ->and($plan->is_active)->toBeFalse()->and($plan->features()->count())->toBe(0);
    expect(Activity::forSubject($plan)->where('event', 'plan_created')->count())->toBe(1);
    $this->getJson(route('central.plans.show', $plan->uuid))->assertForbidden();
});

test('creation is denied without its own permission including direct service calls', function (): void {
    $actor = planCreationActor('saas.plans.view');
    $this->actingAs($actor, 'central')->postJson(route('central.plans.store'), planCreationPayload())->assertForbidden();
    expect(fn () => app(PlanCreationService::class)->create($actor, planCreationPayload()))->toThrow(AuthorizationException::class);
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
    [['features' => [['limit' => 5]]], 'features'],
]);
