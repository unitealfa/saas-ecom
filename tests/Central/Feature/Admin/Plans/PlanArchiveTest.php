<?php

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\User;
use App\Services\Central\Plans\PlanArchiveService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

function planArchiveActor(array $permissions): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('plan-archive-'.Str::random(10), array_fill_keys($permissions, 7)));

    return $actor;
}

function planArchiveState(Plan $plan): string
{
    return $plan->compositionState($plan->features()->orderBy('feature_id')->get());
}

test('an authorized actor archives an unused plan while preserving its composition and code', function (): void {
    $actor = planArchiveActor(['saas.plans.archive', 'saas.plans.view']);
    $plan = Plan::factory()->create(['is_active' => true]);
    $feature = PlanFeature::factory()->create(['plan_id' => $plan->id]);

    $this->actingAs($actor, 'central')->deleteJson(route('central.plans.archive', $plan->uuid), [
        'expected_state' => planArchiveState($plan),
    ])->assertNoContent();

    $archived = Plan::withTrashed()->where('uuid', $plan->uuid)->firstOrFail();
    expect($archived->trashed())->toBeTrue()->and($archived->is_active)->toBeFalse()
        ->and($archived->code)->toBe($plan->code)->and($archived->features()->firstOrFail()->uuid)->toBe($feature->uuid);
    expect(Activity::forSubject($archived)->where('event', 'plan_archived')->count())->toBe(1);
    $this->getJson(route('central.plans.show', $plan->uuid))->assertOk()->assertJsonPath('data.uuid', $plan->uuid);
});

test('archiving requires its own permission including for direct service calls', function (): void {
    $actor = planArchiveActor(['saas.plans.view']);
    $plan = Plan::factory()->create();
    $payload = ['expected_state' => planArchiveState($plan)];

    $this->actingAs($actor, 'central')->deleteJson(route('central.plans.archive', $plan->uuid), $payload)->assertForbidden();
    expect(fn () => app(PlanArchiveService::class)->archive($actor, $plan, $payload))->toThrow(AuthorizationException::class);
    expect($plan->refresh()->trashed())->toBeFalse();
});

test('archiving rejects used, stale and protected free plan versions', function (): void {
    $actor = planArchiveActor(['saas.plans.archive']);
    $owner = User::factory()->create();
    $used = Plan::factory()->create();
    Subscription::factory()->create([
        'user_id' => $owner->id, 'plan_id' => $used->id, 'status' => StatusEnum::EXPIRED,
        'started_at' => now()->subDays(10), 'period_starts_at' => now()->subDays(10),
        'period_ends_at' => now()->subDay(), 'ended_at' => now()->subDay(),
    ]);
    $stale = Plan::factory()->create();
    $oldState = planArchiveState($stale);
    $stale->update(['name' => 'Modifié']);
    $free = Plan::factory()->create(['code' => 'free']);

    $this->actingAs($actor, 'central')->deleteJson(route('central.plans.archive', $used->uuid), [
        'expected_state' => planArchiveState($used),
    ])->assertUnprocessable();
    $this->deleteJson(route('central.plans.archive', $stale->uuid), ['expected_state' => $oldState])->assertUnprocessable();
    $this->deleteJson(route('central.plans.archive', $free->uuid), ['expected_state' => planArchiveState($free)])->assertForbidden();
    expect($used->refresh()->trashed())->toBeFalse()->and($stale->refresh()->trashed())->toBeFalse();
});
