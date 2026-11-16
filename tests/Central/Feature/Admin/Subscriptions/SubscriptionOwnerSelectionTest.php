<?php

use App\Enums\Central\Subscriptions\StatusEnum as SubscriptionStatus;
use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

test('assignment searches paginated merchants and submits the selected owner', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create(['email' => 'staff-only@example.com']);
    $actor->assignRole(Role::createWithPermissions('subscription-selector', ['saas.subscriptions.assign' => 7, 'saas.payments.validate' => 7]));
    $owner = User::factory()->create(['first_name' => 'Karim', 'last_name' => 'Atman', 'email' => 'karim-selector@example.com']);
    User::factory()->count(11)->create();
    User::factory()->create(['first_name' => 'Karim', 'last_name' => 'Suspended', 'status' => StatusEnum::SUSPENDED]);
    $archived = User::factory()->create(['first_name' => 'Karim', 'last_name' => 'Archived']);
    $archived->delete();
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '500.00']);
    $this->actingAs($actor, 'central');

    $this->get(route('central.subscriptions.create'))->assertOk()->assertDontSee('UUID du commerçant');
    $selector = Livewire::test('central::components.subscriptions.owner-selector');
    expect($selector->get('candidates')['owners']->count())->toBe(10)
        ->and($selector->get('candidates')['owners']->total())->toBe(12);
    $selector->call('setPage', 2, 'ownersPage');
    expect($selector->get('candidates')['owners']->count())->toBe(2);

    foreach (['Karim Atman', 'karim-selector@example.com'] as $search) {
        $selector->set('search', $search)->assertSee($owner->email)->assertDontSee('Suspended')->assertDontSee('Archived');
        expect($selector->get('candidates')['owners']->pluck('uuid')->all())->toBe([$owner->uuid])
            ->and($selector->get('candidates')['owners']->currentPage())->toBe(1);
    }
    $selector->set('ownerUuid', $owner->uuid)->set('search', 'nothing-found')
        ->assertSee('(sélectionné)')->assertSee($owner->email)->assertSet('ownerUuid', $owner->uuid);
    $this->get(route('central.subscriptions.create', ['owner' => $owner->uuid, 'search' => 'nothing-found']))
        ->assertOk()->assertSee('(sélectionné)')->assertSee($owner->email);
    $this->get(route('central.subscriptions.create', ['search' => 'staff-only']))
        ->assertOk()->assertSee('Aucun commerçant actif');
    $this->get(route('central.subscriptions.create', ['search' => '%']))
        ->assertOk()->assertSee('Aucun commerçant actif');

    $f = manualPaymentFixture(issued: false);
    $owner->forceFill($f['owner']->only(['legal_address', 'nif', 'legal_verification_status', 'legal_verified_at', 'legal_verified_by_id', 'legal_profile_version']))->save();
    config(['central.billing.customers.'.$owner->uuid => [...config('central.billing.customers.'.$f['owner']->uuid), 'name' => $owner->name]]);
    $this->post(route('central.subscriptions.store'), [
        'owner_uuid' => $owner->uuid, 'plan_uuid' => $plan->uuid, 'period' => 1,
        'operation_key' => (string) Str::uuid(), 'expected_amount' => '500.00', 'received' => true, 'reference' => 'TEST-SELECTION', 'occurred_at' => now()->toDateTimeString(),
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect(Subscription::where('user_id', $owner->id)->where('plan_id', $plan->id)->sole()->status)
        ->toBe(SubscriptionStatus::ACTIVE);
});

test('merchant lookup refuses unauthorized readers and invalid search parameters', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('subscription-reader-selector', ['saas.subscriptions.view' => 7]));
    $this->actingAs($actor, 'central')->get(route('central.subscriptions.create', ['search' => 'Karim']))->assertForbidden();
    Livewire::test('central::components.subscriptions.owner-selector')->assertForbidden();
    $actor->givePermissionTo(['saas.subscriptions.assign', 'saas.payments.validate']);
    $this->get(route('central.subscriptions.create', ['search' => ['invalid']]))->assertSessionHasErrors('search');
    $this->get(route('central.subscriptions.create', ['owner' => 'invalid']))->assertSessionHasErrors('owner');
    $selector = Livewire::test('central::components.subscriptions.owner-selector');
    $selector->set('search', str_repeat('a', 256))->assertHasErrors('search');
    $actor->revokePermissionTo('saas.subscriptions.assign');
    $selector->set('search', 'Karim')->assertForbidden();
});
