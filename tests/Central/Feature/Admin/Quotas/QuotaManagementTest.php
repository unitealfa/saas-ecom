<?php

use App\Models\Central\Roles\Role;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\PlanFeature;
use App\Enums\VerificationStatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Services\Central\Subscriptions\QuotaService;
use App\Services\Central\Tenants\TenantProfileProjection;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Events\TenantCreated;

function quotaActor(string $permission): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('quota-'.Str::random(10), [$permission => 7]));

    return $actor;
}

test('quota readers can see the selected merchant usage and archived shops', function (): void {
    Event::fake([TenantCreated::class]);
    $actor = quotaActor('saas.quotas.view');
    $subscription = Subscription::factory()->create();
    Tenant::factory()->create(['user_id' => $subscription->user_id]);
    $archived = Tenant::factory()->create(['user_id' => $subscription->user_id]);
    DB::table('tenants')->where('id', $archived->id)->update(['status' => TenantStatus::DELETED->value, 'deleted_at' => now()]);
    $feature = Feature::factory()->create(['name' => 'Consommation témoin']);
    DB::table('feature_usage')->insert(['uuid' => (string) Str::uuid(), 'user_id' => $subscription->user_id, 'feature_id' => $feature->id,
        'period_starts_at' => now()->subDay(), 'quantity' => 7, 'created_at' => now(), 'updated_at' => now()]);
    $response = $this->actingAs($actor, 'central')->get(route('central.quotas.show', $subscription->owner));
    $response->assertOk()->assertSee('Boutiques existantes : 1')->assertSee('Boutiques archivées : 1')->assertSee('Consommation témoin');
});

test('reconciliation applies the current quota and records an audit under its dedicated permission', function (): void {
    Event::fake([TenantCreated::class]);
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('quota-'.Str::random(10), ['saas.quotas.view' => 7, 'saas.quotas.reconcile' => 7, 'saas.quotas.history.view' => 7]));
    $reviewer = User::factory()->create();
    $owner = User::factory()->create(['legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now(), 'legal_verified_by_id' => $reviewer->id]);
    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create(['code' => 'shops', 'is_active' => true]);
    PlanFeature::factory()->create(['plan_id' => $plan->id, 'feature_id' => $feature->id, 'is_active' => true, 'limit' => 1]);
    $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'period_ends_at' => now()->addMonth()]);
    $first = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE, 'schema_version' => 'ready', 'provisioned_at' => now(), 'activation_priority' => 1]);
    $second = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE, 'schema_version' => 'ready', 'provisioned_at' => now(), 'activation_priority' => 2]);
    $suspended = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::SUSPENDED, 'schema_version' => 'ready', 'provisioned_at' => now()]);
    $this->mock(TenantProfileProjection::class)->shouldReceive('assertPrepared')->once();
    $state = app(QuotaService::class)->snapshot($actor, $owner)['state'];
    $this->actingAs($actor, 'central')->post(route('central.quotas.reconcile', $owner), ['expected_state' => $state])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('activity_log', ['event' => 'quota_reconciled', 'subject_id' => $owner->id]);
    expect($first->fresh()->status)->toBe(TenantStatus::ACTIVE)->and($second->fresh()->status)->toBe(TenantStatus::OVER_QUOTA)
        ->and($suspended->fresh()->status)->toBe(TenantStatus::SUSPENDED);
    $this->get(route('central.quotas.history', $owner))->assertOk()->assertSee('Réconciliation des quotas demandée')
        ->assertSee('Choix des boutiques actives sous quota mis à jour')->assertSee('Hors quota');
    $this->post(route('central.quotas.reconcile', $owner), ['expected_state' => $state])->assertSessionHasErrors('expected_state');
});

test('quota actions have independent permissions', function (): void {
    $actor = quotaActor('saas.quotas.view');
    $owner = User::factory()->create();
    $this->actingAs($actor, 'central')->post(route('central.quotas.reconcile', $owner))->assertForbidden();
    $this->get(route('central.quotas.history', $owner))->assertForbidden();
});

test('reconciliation resolves an expired subscription and applies the free quota once', function (): void {
    Event::fake([TenantCreated::class]);
    test()->seed([AuthorizationSeeder::class, FreePlanSeeder::class]);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('quota-'.Str::random(10), ['saas.quotas.view' => 7, 'saas.quotas.reconcile' => 7]));
    $reviewer = User::factory()->create();
    $owner = User::factory()->create(['legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now(), 'legal_verified_by_id' => $reviewer->id]);
    $expired = Subscription::factory()->create(['user_id' => $owner->id, 'period_starts_at' => now()->subMonths(2), 'period_ends_at' => now()->subMonth()]);
    Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE, 'schema_version' => 'ready', 'provisioned_at' => now()]);
    $this->mock(TenantProfileProjection::class)->shouldReceive('assertPrepared')->once();
    $state = app(QuotaService::class)->snapshot($actor, $owner)['state'];
    $this->actingAs($actor, 'central')->post(route('central.quotas.reconcile', $owner), ['expected_state' => $state])->assertSessionHasNoErrors();
    expect($expired->fresh()->status)->toBe(\App\Enums\Central\Subscriptions\StatusEnum::EXPIRED)
        ->and(Subscription::where('user_id', $owner->id)->where('status', 3)->firstOrFail()->plan->code)->toBe('free');
});
