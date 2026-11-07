<?php

use App\Enums\Central\Tenants\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Subscriptions\QuotaService;
use App\Services\Central\Tenants\TenantProfileProjection;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('reconciliation returns to central context when a shop profile is missing', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(AuthorizationSeeder::class);
    Event::fake([TenantCreated::class]);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('quota-context-'.Str::random(10), ['saas.quotas.view' => 7, 'saas.quotas.reconcile' => 7]));
    $reviewer = User::factory()->create();
    $owner = User::factory()->create(['legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now(), 'legal_verified_by_id' => $reviewer->id]);
    $plan = Plan::factory()->create();
    $feature = Feature::factory()->create(['code' => 'shops', 'is_active' => true]);
    PlanFeature::factory()->create(['plan_id' => $plan->id, 'feature_id' => $feature->id, 'is_active' => true, 'limit' => 1]);
    Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'period_ends_at' => now()->addMonth()]);
    $shop = Tenant::factory()->create(['user_id' => $owner->id, 'status' => StatusEnum::ACTIVE, 'schema_version' => 'ready', 'provisioned_at' => now()]);
    $database = $shop->database();
    $database->manager()->createDatabase($shop);
    try {
        tenancy()->initialize($shop);
        Schema::connection('tenant')->create('shop', function (Blueprint $table): void {
            $table->unsignedTinyInteger('singleton');
            $table->uuid('tenant_uuid');
        });
        Schema::connection('tenant')->create('users', function (Blueprint $table): void {
            $table->uuid('central_user_uuid');
            $table->softDeletes();
        });
        tenancy()->end();
        $state = app(QuotaService::class)->snapshot($actor, $owner)['state'];
        $this->actingAs($actor, 'central')->post(route('central.quotas.reconcile', $owner), ['expected_state' => $state])
            ->assertRedirect(route('central.quotas.show', $owner))->assertSessionHasNoErrors();
        expect(tenancy()->initialized)->toBeFalse()->and(DB::getDefaultConnection())->toBe('mysql')
            ->and($shop->fresh()->status)->toBe(StatusEnum::INACTIVE);
        $this->actingAs($actor, 'central')->get(route('central.quotas.show', $owner))->assertOk();
        expect(fn () => app(TenantProfileProjection::class)->sync($shop))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        expect(tenancy()->initialized)->toBeFalse()->and(DB::getDefaultConnection())->toBe('mysql');
        tenancy()->initialize($shop);
        DB::connection('tenant')->table('shop')->insert(['singleton' => 1, 'tenant_uuid' => $shop->uuid]);
        DB::connection('tenant')->table('users')->insert(['central_user_uuid' => $owner->uuid]);
        tenancy()->end();
        $shop->refresh()->forceFill(['status' => StatusEnum::ACTIVE])->save();
        $state = app(QuotaService::class)->snapshot($actor, $owner)['state'];
        $this->actingAs($actor, 'central')->post(route('central.quotas.reconcile', $owner), ['expected_state' => $state])
            ->assertRedirect(route('central.quotas.show', $owner))->assertSessionHasNoErrors();
        expect(tenancy()->initialized)->toBeFalse()->and($shop->fresh()->status)->toBe(StatusEnum::ACTIVE);
    } finally {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        TestCase::dropIsolatedMysqlDatabase($database->getName());
    }
});
