<?php

use App\Models\Central\Audit\Activity;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\User;
use App\Services\Central\Plans\PlanVersionService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

function planVersionActor(array $permissions): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('plan-version-'.Str::random(10), array_fill_keys($permissions, 7)));

    return $actor;
}

function planVersionPayload(Plan $plan, int $latestVersion): array
{
    return [
        'expected_state' => $plan->compositionState($plan->features()->orderBy('feature_id')->get()),
        'expected_latest_version' => $latestVersion,
    ];
}

test('a new inactive version copies the source without changing its historical subscriptions', function (): void {
    $actor = planVersionActor(['saas.plans.view', 'saas.plans.versions.create']);
    $owner = User::factory()->create();
    $source = Plan::factory()->create(['code' => 'pro', 'version' => 1, 'name' => 'Pro', 'monthly_price' => '3000.00']);
    $feature = Feature::factory()->create();
    $grant = PlanFeature::factory()->create(['plan_id' => $source->id, 'feature_id' => $feature->id, 'limit' => 5]);
    $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $source->id]);
    $old = Plan::factory()->create(['code' => 'pro', 'version' => 2]);
    $old->delete();
    $payload = planVersionPayload($source, 2);

    $this->actingAs($actor, 'central')->getJson(route('central.plans.show', $source->uuid))
        ->assertOk()->assertJsonPath('data.latest_version', 2);
    $response = $this->postJson(route('central.plans.versions.store', $source->uuid), $payload)
        ->assertCreated()->assertJsonStructure(['data' => ['uuid']])->assertJsonMissingPath('data.id');
    $created = Plan::where('uuid', $response->json('data.uuid'))->firstOrFail();
    $copy = $created->features()->firstOrFail();

    expect($created->code)->toBe('pro')->and($created->version)->toBe(3)
        ->and($created->is_active)->toBeFalse()->and($created->monthly_price)->toBe('3000.00')
        ->and($copy->feature_id)->toBe($feature->id)->and($copy->limit)->toBe(5)
        ->and($copy->uuid)->not->toBe($grant->uuid)->and($subscription->refresh()->plan_id)->toBe($source->id);
    expect(Activity::forSubject($created)->where('event', 'plan_version_created')->count())->toBe(1);
    $this->postJson(route('central.plans.versions.store', $source->uuid), $payload)->assertUnprocessable();
    expect(Plan::withTrashed()->where('code', 'pro')->count())->toBe(3);
});

test('version creation requires its own permission also for direct service calls', function (): void {
    $actor = planVersionActor(['saas.plans.view']);
    $source = Plan::factory()->create();
    $payload = planVersionPayload($source, 1);
    $this->actingAs($actor, 'central')->postJson(route('central.plans.versions.store', $source->uuid), $payload)->assertForbidden();
    expect(fn () => app(PlanVersionService::class)->create($actor, $source, $payload))->toThrow(AuthorizationException::class);
});

test('a version creator does not receive plan consultation rights', function (): void {
    $actor = planVersionActor(['saas.plans.versions.create']);
    $source = Plan::factory()->create();
    $response = $this->actingAs($actor, 'central')->postJson(route('central.plans.versions.store', $source->uuid), planVersionPayload($source, 1))
        ->assertCreated()->assertJsonMissingPath('data.code');
    $this->getJson(route('central.plans.show', $response->json('data.uuid')))->assertForbidden();
});

test('version creation refuses source features that are no longer available', function (): void {
    $actor = planVersionActor(['saas.plans.versions.create']);
    $source = Plan::factory()->create();
    $feature = Feature::factory()->create();
    PlanFeature::factory()->create(['plan_id' => $source->id, 'feature_id' => $feature->id]);
    $feature->forceFill(['is_active' => false])->save();

    $this->actingAs($actor, 'central')->postJson(route('central.plans.versions.store', $source->uuid), planVersionPayload($source, 1))
        ->assertUnprocessable()->assertJsonValidationErrors('features');
    expect(Plan::where('code', $source->code)->count())->toBe(1);
});

test('version creation rejects stale sources, protected plans and altered attributes', function (): void {
    $actor = planVersionActor(['saas.plans.versions.create']);
    $source = Plan::factory()->create();
    $stale = planVersionPayload($source, 1);
    $source->update(['name' => 'Modifié']);
    $this->actingAs($actor, 'central')->postJson(route('central.plans.versions.store', $source->uuid), $stale)->assertUnprocessable();
    $this->postJson(route('central.plans.versions.store', $source->uuid), array_replace(planVersionPayload($source, 1), ['monthly_price' => 1]))
        ->assertUnprocessable()->assertJsonValidationErrors('monthly_price');
    $free = Plan::factory()->create(['code' => 'free']);
    $this->postJson(route('central.plans.versions.store', $free->uuid), planVersionPayload($free, 1))->assertForbidden();
    expect(Plan::where('code', $source->code)->count())->toBe(1);
});

test('version creation rejects an exhausted version number', function (): void {
    $actor = planVersionActor(['saas.plans.versions.create']);
    $source = Plan::factory()->create(['version' => 2147483647]);

    $this->actingAs($actor, 'central')->postJson(route('central.plans.versions.store', $source->uuid), planVersionPayload($source, 2147483647))
        ->assertUnprocessable()->assertJsonValidationErrors('plan');
    expect(Plan::where('code', $source->code)->count())->toBe(1);
});
