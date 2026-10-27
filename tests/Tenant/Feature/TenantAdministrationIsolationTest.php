<?php

use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Subscriptions\StatusEnum as SubscriptionStatus;
use App\Enums\Central\Tenants\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Jobs\Central\SyncTenantProfile;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Users\User as ShopUser;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

test('tenant administration provisions isolated databases rebalances dated quotas projects names and archives without dropping data', function (): void {
    Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    $this->seed([DatabaseSeeder::class, AuthorizationSeeder::class]);
    $this->freezeTime();
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));
    $owner = User::factory()->create([
        'legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => $admin->id,
    ]);
    $plan = Plan::factory()->create();
    PlanFeature::create(['plan_id' => $plan->id, 'feature_id' => Feature::where('code', 'shops')->firstOrFail()->id,
        'is_active' => true, 'limit' => 3]);
    $paid = Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE, 'period_ends_at' => now()->addDay()]);
    $shops = [];
    $databases = [];
    $initialProfiles = [];

    try {
        foreach (['module05-a', 'module05-b', 'module05-c'] as $slug) {
            $this->actingAs($admin, 'central')->post(route('central.users.tenants.store', $owner), [
                'shop_name' => $slug, 'slug' => $slug, 'creation_key' => (string) Str::uuid(),
            ])->assertSessionHasNoErrors()->assertRedirect();
            $shop = Tenant::where('slug', $slug)->firstOrFail();
            $shops[] = $shop;
            $databases[] = $shop->database()->getName();
            expect($shop->database()->manager()->databaseExists($shop->database()->getName()))->toBeFalse();
        }
        expect(DB::table('jobs')->count())->toBe(3);
        $domain = $shops[0]->domains()->firstOrFail()->domain;
        $this->get('http://'.$domain.'/')->assertStatus(503);
        expect(tenancy()->initialized)->toBeFalse();
        Artisan::call('queue:work', ['connection' => 'central-provisioning', '--stop-when-empty' => true, '--no-interaction' => true]);
        expect(DB::table('failed_jobs')->count())->toBe(0)
            ->and(TenantSchemaDeployment::where('status', DeploymentStatus::SUCCEEDED)->count())->toBe(3);

        foreach ($shops as $shop) {
            $shop->refresh();
            expect($shop->provisioned_at)->not->toBeNull()->and($shop->schema_version)->not->toBeNull();
            $initialProfiles[$shop->uuid] = $shop->run(function () use ($shop, $owner): array {
                expect(Shop::count())->toBe(1);
                $profile = Shop::firstOrFail();
                expect($profile->tenant_uuid)->toBe($shop->uuid)->and($profile->shop_name)->toBe($shop->shop_name);
                expect(ShopUser::firstOrFail()->central_user_uuid)->toBe($owner->uuid);

                return $profile->getAttributes();
            });
            expect($shop->status)->toBe(StatusEnum::ACTIVE);
            $this->actingAs($admin, 'central')->post(route('central.tenants.activate', $shop), ['expected_status' => StatusEnum::PROVISIONING->value])
                ->assertForbidden();
            expect($shop->fresh()->status)->toBe(StatusEnum::ACTIVE);
        }
        expect(Tenant::where('status', StatusEnum::ACTIVE)->count())->toBe(3);

        $this->travel(2)->days();
        $rank = [$shops[2]->uuid => 1, $shops[1]->uuid => 2, $shops[0]->uuid => 3];
        $this->actingAs($admin, 'central')->put(route('central.tenants.priorities', $shops[0]), ['priorities' => $rank, 'reason' => 'Expired paid plan'])
            ->assertSessionHasNoErrors();
        expect($paid->fresh()->status)->toBe(SubscriptionStatus::EXPIRED)
            ->and($shops[0]->fresh()->status)->toBe(StatusEnum::OVER_QUOTA)
            ->and($shops[1]->fresh()->status)->toBe(StatusEnum::ACTIVE)
            ->and($shops[2]->fresh()->status)->toBe(StatusEnum::ACTIVE);
        expect(Subscription::where('user_id', $owner->id)->where('status', SubscriptionStatus::ACTIVE)->firstOrFail()->plan->code)->toBe('free');

        $this->actingAs($admin, 'central')->post(route('central.tenants.suspend', $shops[1]), ['expected_status' => StatusEnum::ACTIVE->value])->assertSessionHasNoErrors();
        $this->get('http://'.$shops[1]->domains()->firstOrFail()->domain.'/')->assertServiceUnavailable()->assertDontSee('ID de la boutique');
        $this->get('http://'.$shops[1]->domains()->firstOrFail()->domain.'/_dev/database')->assertForbidden();
        expect(tenancy()->initialized)->toBeFalse();
        $rank = [$shops[1]->uuid => 1, $shops[0]->uuid => 2, $shops[2]->uuid => 3];
        $this->actingAs($admin, 'central')->put(route('central.tenants.priorities', $shops[0]), ['priorities' => $rank])->assertSessionHasNoErrors();
        expect($shops[1]->fresh()->status)->toBe(StatusEnum::SUSPENDED)
            ->and($shops[0]->fresh()->status)->toBe(StatusEnum::ACTIVE);
        $this->actingAs($admin, 'central')->post(route('central.tenants.activate', $shops[1]), ['expected_status' => StatusEnum::SUSPENDED->value])->assertSessionHasNoErrors();
        expect($shops[1]->fresh()->status)->toBe(StatusEnum::ACTIVE)
            ->and($shops[2]->fresh()->status)->toBe(StatusEnum::OVER_QUOTA);
        $this->get('http://'.$shops[1]->domains()->firstOrFail()->domain.'/')->assertStatus(503);
        expect(tenancy()->initialized)->toBeFalse();

        $identity = $shops[0]->fresh()->only(['id', 'uuid', 'user_id', 'slug', 'data', 'document_prefix', 'creation_key', 'creation_hash']);
        foreach ([1 => 'First name', 2 => 'Latest name'] as $version => $name) {
            $this->actingAs($admin, 'central')->put(route('central.tenants.update', $shops[0]), [
                'shop_name' => $name, 'internal_label' => 'Central label', 'expected_profile_version' => $version,
            ])->assertSessionHasNoErrors();
        }
        expect(DB::table('jobs')->count())->toBe(2);
        Artisan::call('queue:work', ['connection' => 'central-provisioning', '--stop-when-empty' => true, '--no-interaction' => true]);
        expect(DB::table('failed_jobs')->count())->toBe(0)->and(DB::table('jobs')->count())->toBe(0);
        app()->call([new SyncTenantProfile($shops[0]->id, $shops[0]->uuid), 'handle']);
        app()->call([new SyncTenantProfile($shops[0]->id, $shops[1]->uuid), 'handle']);
        foreach ($shops as $index => $shop) {
            $actual = $shop->run(fn (): array => Shop::firstOrFail()->getAttributes());
            if ($index === 0) {
                expect($actual['shop_name'])->toBe('Latest name')->and($actual['central_profile_version'])->toBe(3);
                $preserved = array_diff_key($initialProfiles[$shop->uuid], array_flip(['shop_name', 'central_profile_version', 'updated_at']));
                expect(array_intersect_key($actual, $preserved))->toBe($preserved);
            } else {
                expect($actual)->toBe($initialProfiles[$shop->uuid]);
            }
        }
        expect($shops[0]->fresh()->only(array_keys($identity)))->toBe($identity);

        $this->actingAs($admin, 'central')->post(route('central.tenants.archive', $shops[0]), ['expected_status' => StatusEnum::ACTIVE->value, 'reason' => 'Preserve database'])
            ->assertSessionHasNoErrors();
        $archived = Tenant::withTrashed()->findOrFail($shops[0]->id);
        expect($archived->trashed())->toBeTrue()->and($archived->status)->toBe(StatusEnum::DELETED);
        $this->get('http://'.$domain.'/')->assertNotFound();
        $this->actingAs($admin, 'central')->get(route('central.tenants.history', $archived))->assertOk()->assertSee('Preserve database');
        foreach ($shops as $shop) {
            expect($shop->database()->manager()->databaseExists($shop->database()->getName()))->toBeTrue();
            $shop->run(function () use ($shop, $owner): void {
                expect(Shop::firstOrFail()->tenant_uuid)->toBe($shop->uuid);
                expect(ShopUser::firstOrFail()->central_user_uuid)->toBe($owner->uuid);
            });
        }
    } finally {
        tenancy()->end();
        foreach ($databases as $database) {
            TestCase::dropIsolatedMysqlDatabase($database);
        }
    }
});
