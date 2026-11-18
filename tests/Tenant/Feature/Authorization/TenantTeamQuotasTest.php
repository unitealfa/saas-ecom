<?php

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Users\User as TenantUser;
use App\Models\User as CentralUser;
use App\Services\Tenant\Authorization\TenantAccess;
use App\Services\Tenant\Authorization\TenantQuotaService;
use Database\Seeders\Central\TenantTeamQuotaFeatureSeeder;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

beforeEach(function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
});

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
    }
});

test('member quota counts each boutique independently and does not delete existing members after a reduction', function (): void {
    $this->seed(TenantTeamQuotaFeatureSeeder::class);
    $owner = CentralUser::factory()->create();
    $plan = Plan::factory()->create();
    $feature = Feature::where('code', Feature::TEAM_MEMBERS_CODE)->firstOrFail();
    // Fixture uniquement : une limite choisie dans le test, jamais un chiffre fixé pour une offre réelle.
    PlanFeature::factory()->create([
        'plan_id' => $plan->id,
        'feature_id' => $feature->id,
        'is_active' => true,
        'limit' => 1,
    ]);
    Subscription::factory()->create([
        'user_id' => $owner->id,
        'plan_id' => $plan->id,
        'period_starts_at' => now()->subDay(),
        'period_ends_at' => now()->addMonth(),
    ]);
    $one = Tenant::factory()->create(['user_id' => $owner->id]);
    $two = Tenant::factory()->create(['user_id' => $owner->id]);

    $one->run(function (): void {
        $member = TenantUser::factory()->create(['email' => 'member-one@example.test']);
        expect(fn () => app(TenantQuotaService::class)->assertCanReserveSeat('member-two@example.test'))
            ->toThrow(ValidationException::class, 'Limite de l’offre atteinte : 1 membre(s) maximum.');
        expect(TenantUser::whereKey($member->id)->exists())->toBeTrue();
    });
    $two->run(function (): void {
        expect(fn () => app(TenantQuotaService::class)->assertCanReserveSeat('member-two@example.test'))
            ->not->toThrow(ValidationException::class);
    });
});

test('role access follows the plan and role limits are independent for each shop', function (): void {
    $this->seed(TenantTeamQuotaFeatureSeeder::class);
    $owner = CentralUser::factory()->create();
    $plan = Plan::factory()->create();
    $feature = Feature::where('code', Feature::TEAM_CUSTOM_ROLES_CODE)->firstOrFail();
    PlanFeature::factory()->create(['plan_id' => $plan->id, 'feature_id' => $feature->id, 'is_active' => true, 'limit' => 1]);
    Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id,
        'period_starts_at' => now()->subDay(), 'period_ends_at' => now()->addMonth()]);
    $first = Tenant::factory()->create(['user_id' => $owner->id]);
    $second = Tenant::factory()->create(['user_id' => $owner->id]);
    foreach ([$first, $second] as $shop) {
        $shop->run(function (): void {
            $actor = TenantUser::factory()->create();
            $actor->forceFill(['status' => StatusEnum::ACTIVE,
                'membership_status' => MembershipStatusEnum::ACTIVE,
                'joined_at' => now(), 'email_verified_at' => now()])->save();
            $actor->givePermissionTo('shop.roles.view');
            expect(TenantAccess::allows($actor, 'shop.roles.view'))->toBeTrue();
            app(TenantQuotaService::class)->assertCanCreateCustomRole();
            Role::createWithPermissions('test-profile', ['shop.roles.view' => 30]);
            expect(fn () => app(TenantQuotaService::class)->assertCanCreateCustomRole())->toThrow(ValidationException::class);
        });
    }

    $ownerWithoutRoles = CentralUser::factory()->create();
    $emptyPlan = Plan::factory()->create();
    Subscription::factory()->create(['user_id' => $ownerWithoutRoles->id, 'plan_id' => $emptyPlan->id,
        'period_starts_at' => now()->subDay(), 'period_ends_at' => now()->addMonth()]);
    Tenant::factory()->create(['user_id' => $ownerWithoutRoles->id])->run(function (): void {
        $actor = TenantUser::factory()->create();
        $actor->forceFill(['status' => StatusEnum::ACTIVE,
            'membership_status' => MembershipStatusEnum::ACTIVE,
            'joined_at' => now(), 'email_verified_at' => now()])->save();
        $actor->givePermissionTo('shop.roles.view');
        expect(TenantAccess::allows($actor, 'shop.roles.view'))->toBeFalse();
    });
});
