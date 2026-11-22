<?php

use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Users\User as LocalUser;
use App\Models\User;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Tenant\Auth\OwnerLoginService;
use App\Services\Tenant\Authorization\AuthorizationUpgradeService;
use App\Services\Tenant\Authorization\TenantAuthorizationMode;
use App\Services\Tenant\Authorization\TenantPermissionCatalog;
use Database\Factories\Central\DomainFactory;
use Database\Seeders\Central\FreePlanSeeder;
use Database\Seeders\Central\TenantTeamQuotaFeatureSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
    }
});

test('verified owners enter their shop once through a browser-bound signed grant', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(FreePlanSeeder::class);
    $this->seed(TenantTeamQuotaFeatureSeeder::class);
    $plan = Plan::factory()->create(['name' => 'roles Free', 'is_active' => true,
        'monthly_price' => '0.00', 'annual_price' => '0.00']);
    foreach ([Feature::SHOPS_CODE => 2, Feature::TEAM_CUSTOM_ROLES_CODE => 3, Feature::TEAM_MEMBERS_CODE => 2] as $code => $limit) {
        PlanFeature::factory()->create(['plan_id' => $plan->id,
            'feature_id' => Feature::where('code', $code)->sole()->id, 'limit' => $limit, 'is_active' => true]);
    }
    $plan->forceFill(['is_default' => true])->save();
    $admin = User::factory()->create();
    $owner = User::factory()->create(['legal_verified_by_id' => $admin->id, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_profile_version' => 1]);
    expect(app(DefaultSubscription::class)->ensure($owner)->plan_id)->toBe($plan->id);
    $tenant = Tenant::factory()->create(['user_id' => $owner->id]);
    $tenant->forceFill(['status' => TenantStatus::ACTIVE])->save();
    $domain = DomainFactory::new()->create(['tenant_id' => $tenant->id,
        'domain' => $tenant->slug.'.aydra.localhost', 'is_primary' => true,
        'verification_status' => VerificationStatusEnum::VERIFIED, 'verified_at' => now()]);
    config(['session.driver' => 'database']);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    $base = rtrim($domain->publicUrl(), '/');
    $this->get($base)->assertOk()->assertSee($tenant->shop_name);
    $cookieName = 'aydra_tenant_'.$tenant->id;
    $this->get($base.'/auth/owner/start')->assertForbidden();
    $entry = route('owner.shops.access', $tenant);
    $start = $this->actingAs($owner, 'central')->get($entry)->assertRedirect()->headers->get('Location');
    expect($start)->toStartWith($base.'/auth/owner/start?');
    $handoff = $this->get($start)->assertRedirect();
    $access = $handoff->headers->get('Location');
    $cookie = $handoff->getCookie($cookieName, decrypt: false);
    expect($cookie)->not->toBeNull();
    $this->withUnencryptedCookie($cookieName, $cookie->getValue());
    $grant = $this->actingAs($owner, 'central')->get($access)->assertRedirect();
    $target = $grant->headers->get('Location');
    expect(str_starts_with($target, $base.'/auth/owner?'))->toBeTrue();
    expect(URL::hasValidSignature(Request::create($target), absolute: false))->toBeTrue();
    $this->withoutExceptionHandling();
    $accepted = $this->get($target)->assertRedirect($base.'/admin');
    $this->withUnencryptedCookie($cookieName, $accepted->getCookie($cookieName, decrypt: false)->getValue());
    $this->withExceptionHandling();
    $this->get($base.'/admin')->assertOk()->assertSee('Profil de la boutique')
        ->assertSee('Personnel')->assertSee('Profils et rôles')->assertSee('Permissions')->assertSee('Équipe');
    $this->get($base.'/admin/shop/profile')->assertOk();
    $this->get($base.'/admin/permissions')->assertOk();
    $this->get($target)->assertForbidden();
    $tenant->run(function () use ($owner): void {
        $local = LocalUser::where('central_user_uuid', $owner->uuid)->sole();
        expect(TenantAuthorizationMode::individualOnly())->toBeTrue()
            ->and($local->can('shop.roles.view'))->toBeTrue()
            ->and($local->can('shop.permissions.view'))->toBeTrue()
            ->and($local->can('shop.users.view'))->toBeTrue()
            ->and($local->can('shop.profile.update'))->toBeTrue()
            ->and($local->membership_status)->toBe(MembershipStatusEnum::ACTIVE)
            ->and($local->joined_at)->not->toBeNull();
        $local->revokePermissionTo('shop.profile.update');
        foreach (array_keys(TenantPermissionCatalog::ALL) as $permission) {
            $local->revokePermissionTo($permission);
        }
        DB::connection('tenant')->table('tenant_authorization_state')->where('id', 1)->update([
            'individual_only' => false,
            'converted_at' => null,
            'updated_at' => now(),
        ]);
        expect(TenantAuthorizationMode::individualOnly())->toBeFalse();
    });

    $this->get($base.'/admin')->assertOk()
        ->assertDontSee('Gérer les rôles')
        ->assertDontSee('Voir les permissions')
        ->assertDontSee("Gérer l'équipe", false);

    $tenant->run(function () use ($owner): void {
        $result = app(AuthorizationUpgradeService::class)->apply();
        $local = LocalUser::where('central_user_uuid', $owner->uuid)->sole();

        expect($result['ready'])->toBeTrue()
            ->and(TenantAuthorizationMode::individualOnly())->toBeTrue()
            ->and($local->can('shop.roles.view'))->toBeTrue()
            ->and($local->can('shop.permissions.view'))->toBeTrue()
            ->and($local->can('shop.users.view'))->toBeTrue();
    });

    $this->get($base.'/admin')->assertOk()
        ->assertSee('Gérer les rôles')
        ->assertSee('Voir les permissions')
        ->assertSee("Gérer l'équipe", false);

    $tenant->run(function () use ($owner): void {
        $local = LocalUser::where('central_user_uuid', $owner->uuid)->sole();
        $local->revokePermissionTo('shop.roles.view');
        $service = app(OwnerLoginService::class);
        $service->accept($owner->uuid, tenant()->uuid, hash('sha256', $owner->email));

        expect($local->fresh()->can('shop.roles.view'))->toBeFalse()
            ->and($local->fresh()->can('shop.permissions.view'))->toBeTrue();
    });

    $this->get($base.'/admin')->assertOk()
        ->assertDontSee('Gérer les rôles')
        ->assertSee('Voir les permissions')
        ->assertSee("Gérer l'équipe", false);
    $plan->forceFill(['is_default' => null])->save();
    app(DefaultSubscription::class)->ensure($owner);
    $this->get($base.'/admin')->assertOk()
        ->assertDontSee('Personnel')->assertDontSee('Voir les permissions')->assertDontSee("Gérer l'équipe", false);
    $this->get($base.'/admin/permissions')->assertForbidden();

    $other = User::factory()->create(['legal_verified_by_id' => $admin->id, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_profile_version' => 1]);
    $this->actingAs($other, 'central')->get($access)->assertForbidden();
    $this->travel(2)->minutes();
    expect(URL::hasValidSignature(Request::create($target), absolute: false))->toBeFalse();
    $this->get($target)->assertForbidden();
});
