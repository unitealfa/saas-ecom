<?php

use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Str;

function planInterfaceActor(array $permissions): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('plan-interface-'.Str::random(10), array_fill_keys($permissions, 7)));

    return $actor;
}

test('plan pages require consultation permission and show versions', function (): void {
    $plan = Plan::factory()->create(['name' => 'Offre Exemple']);
    $reader = planInterfaceActor(['saas.plans.view']);
    $this->actingAs($reader, 'central')->get(route('central.plan-pages.index'))->assertOk()->assertSee('Offre Exemple');
    $this->get(route('central.plan-pages.show', $plan))->assertOk()->assertSee('Fonctionnalités et quotas');
    $this->get(route('central.plan-pages.create'))->assertForbidden();
});

test('plan information updates require permission and reject stale state', function (): void {
    $plan = Plan::factory()->create(['code' => 'example', 'name' => 'Avant']);
    $actor = planInterfaceActor(['saas.plans.view', 'saas.plans.update']);
    $data = ['name' => 'Après', 'description' => null, 'monthly_price' => '20.00', 'annual_price' => '200.00', 'expected_state' => $plan->compositionState($plan->features)];
    $this->actingAs($actor, 'central')->put(route('central.plan-pages.update', $plan), $data)->assertRedirect();
    expect($plan->fresh()->name)->toBe('Après');
    $this->put(route('central.plan-pages.update', $plan), $data)->assertSessionHasErrors('expected_state');
    $reader = planInterfaceActor(['saas.plans.view']);
    $this->actingAs($reader, 'central')->put(route('central.plan-pages.update', $plan), $data)->assertForbidden();
});

test('used and free plan versions cannot be edited', function (): void {
    $actor = planInterfaceActor(['saas.plans.update']);
    $plan = Plan::factory()->create();
    Subscription::factory()->create(['user_id' => User::factory()->create()->id, 'plan_id' => $plan->id]);
    $data = ['name' => 'Nouveau', 'description' => null, 'monthly_price' => '1.00', 'annual_price' => '10.00', 'expected_state' => $plan->compositionState($plan->features)];
    $this->actingAs($actor, 'central')->putJson(route('central.plans.update', $plan), $data)->assertUnprocessable();
    $free = Plan::factory()->create(['code' => 'free']);
    $this->putJson(route('central.plans.update', $free), $data)->assertForbidden();
});

test('the composition form submits selected features and quotas', function (): void {
    $actor = planInterfaceActor(['saas.plans.view', 'saas.plans.features.update']);
    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create(['is_active' => true]);
    $this->actingAs($actor, 'central')->put(route('central.plan-pages.features.update', $plan), [
        'expected_state' => $plan->compositionState($plan->features),
        'selected' => [$feature->uuid], 'enabled' => [$feature->uuid => '1'], 'limits' => [$feature->uuid => '12'],
    ])->assertRedirect(route('central.plan-pages.show', $plan));
    expect($plan->features()->firstOrFail()->limit)->toBe(12);
});

test('the composition form preserves choices and a rejected quota after validation failure', function (): void {
    $actor = planInterfaceActor(['saas.plans.view', 'saas.plans.features.update']);
    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create(['is_active' => true]);
    $url = route('central.plan-pages.show', $plan);

    $this->actingAs($actor, 'central')->from($url)
        ->put(route('central.plan-pages.features.update', $plan), [
            '_composition' => '1', 'expected_state' => $plan->compositionState($plan->features),
            'selected' => [$feature->uuid], 'enabled' => [$feature->uuid => '1'], 'limits' => [$feature->uuid => '-1'],
        ])->assertRedirect($url)->assertSessionHasErrors('features.0.limit');

    $response = $this->get($url)->assertOk();
    $response->assertSee('name="selected[]" value="'.$feature->uuid.'" checked', false);
    $response->assertSee('name="enabled['.$feature->uuid.']" value="1" checked', false);
    $response->assertSee('name="limits['.$feature->uuid.']" value="-1"', false);
    expect($plan->features()->count())->toBe(0);
});

test('malformed previous composition input still renders the form safely', function (): void {
    $actor = planInterfaceActor(['saas.plans.view', 'saas.plans.features.update']);
    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create(['is_active' => true]);

    $this->actingAs($actor, 'central')->withSession(['_old_input' => [
        '_composition' => '1', 'selected' => 'invalid', 'enabled' => 'invalid',
        'limits' => [$feature->uuid => ['invalid']],
    ]])->get(route('central.plan-pages.show', $plan))->assertOk();
});
