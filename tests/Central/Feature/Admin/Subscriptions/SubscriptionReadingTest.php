<?php

use App\Models\Central\Plans\Plan;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Str;

function subscriptionReader(string $permission): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('subscriptions-'.Str::random(10), [$permission => 7]));

    return $actor;
}

test('authorized staff can search subscriptions and view archived plan details', function (): void {
    $actor = subscriptionReader('saas.subscriptions.view');
    $owner = User::factory()->create(['name' => 'Marchand Exemple']);
    $plan = Plan::factory()->create(['name' => 'Offre Exemple']);
    $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
    $plan->delete();
    $this->actingAs($actor, 'central')->get(route('central.subscriptions.index', ['search' => 'Marchand Exemple']))
        ->assertOk()->assertSee('Offre Exemple');
    $this->get(route('central.subscriptions.show', $subscription))->assertOk()->assertSee('Offre Exemple');
    $this->get(route('central.subscriptions.show', $subscription->id))->assertNotFound();
});

test('subscription consultation requires its own permission', function (): void {
    $actor = subscriptionReader('saas.plans.view');
    $subscription = Subscription::factory()->create();
    $this->actingAs($actor, 'central')->get(route('central.subscriptions.index'))->assertForbidden();
    $this->get(route('central.subscriptions.show', $subscription))->assertForbidden();
});

test('subscription results paginate without exposing unrelated users when searching', function (): void {
    $actor = subscriptionReader('saas.subscriptions.view');
    $owner = User::factory()->create(['name' => 'Nom unique recherche']);
    Subscription::factory()->count(21)->create(['user_id' => $owner->id, 'status' => 4]);
    $this->actingAs($actor, 'central')->get(route('central.subscriptions.index', ['search' => 'Nom unique recherche']))
        ->assertOk()->assertSee('Nom unique recherche');
    $this->get(route('central.subscriptions.index', ['status' => '99']))->assertSessionHasErrors('status');
});

test('a user reader without subscription permission cannot see subscription details on the user page', function (): void {
    $actor = subscriptionReader('saas.users.view');
    $owner = User::factory()->create();
    $plan = Plan::factory()->create(['name' => 'Offre Confidentielle']);
    Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
    $this->actingAs($actor, 'central')->get(route('central.users.show', $owner))
        ->assertOk()->assertDontSee('Offre Confidentielle');
});
