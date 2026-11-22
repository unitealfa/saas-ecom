<?php

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Http\Controllers\Tenant\Admin\Users\UserPermissionController;
use App\Http\Controllers\Tenant\Admin\Users\UserRoleController;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Users\User as TenantUser;
use App\Models\User as CentralUser;
use App\Services\Tenant\Authorization\TenantAuthorizationMode;
use App\Services\Tenant\Authorization\TenantPermissionCatalog;
use Database\Seeders\Central\TenantTeamQuotaFeatureSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
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

test('a removed individual permission does not return from an assigned profile', function (): void {
    $central = CentralUser::factory()->create();
    $shop = Tenant::factory()->create(['user_id' => $central->id, 'slug' => 'tenant-permissions-a']);
    $shop->run(function (): void {
        expect(TenantAuthorizationMode::individualOnly())->toBeTrue();
        $member = TenantUser::factory()->create();
        $member->forceFill(['status' => StatusEnum::ACTIVE,
            'membership_status' => MembershipStatusEnum::ACTIVE,
            'joined_at' => now(), 'email_verified_at' => now()])->save();
        $permission = Permission::firstOrCreate(['name' => 'shop.orders.review', 'guard_name' => 'tenant'],
            ['label' => 'Consulter commandes']);
        $profile = Role::createWithPermissions('order-reviewer', ['shop.orders.review' => 30]);
        $member->assignRole($profile);
        expect($member->hasPermissionTo($permission))->toBeTrue();
        $member->revokePermissionTo($permission);
        expect($member->fresh()->hasPermissionTo($permission))->toBeFalse();
        expect($member->fresh()->can('shop.orders.review'))->toBeFalse();
        expect($member->fresh()->getPermissionsViaRoles())->toBeEmpty();
    });
});

test('same role name and email remain independent in distinct tenant databases', function (): void {
    $central = CentralUser::factory()->create();
    $first = Tenant::factory()->create(['user_id' => $central->id, 'slug' => 'tenant-separation-a']);
    $second = Tenant::factory()->create(['user_id' => $central->id, 'slug' => 'tenant-separation-b']);
    foreach ([$first, $second] as $shop) {
        $shop->run(function (): void {
            Permission::firstOrCreate(['name' => 'shop.example.view', 'guard_name' => 'tenant'],
                ['label' => 'Exemple']);
            Role::createWithPermissions('manager-test', ['shop.example.view' => 7]);
        });
    }
    $first->run(function (): void {
        Role::where('name', 'manager-test')->firstOrFail()->update(['label' => 'Différent']);
    });
    $second->run(function (): void {
        expect(Role::where('name', 'manager-test')->firstOrFail()->label)->not->toBe('Différent');
    });
});

test('viewing a team member does not grant access to role or permission catalogues', function (): void {
    $this->seed(TenantTeamQuotaFeatureSeeder::class);
    $owner = CentralUser::factory()->create();
    $plan = Plan::factory()->create();
    foreach ([Feature::TEAM_CUSTOM_ROLES_CODE, Feature::TEAM_MEMBERS_CODE] as $code) {
        PlanFeature::factory()->create(['plan_id' => $plan->id,
            'feature_id' => Feature::where('code', $code)->sole()->id, 'is_active' => true, 'limit' => 5]);
    }
    Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
    $shop = Tenant::factory()->create(['user_id' => $owner->id]);
    $shop->run(function (): void {
        TenantPermissionCatalog::seed();
        $actor = TenantUser::factory()->create();
        $actor->forceFill(['status' => StatusEnum::ACTIVE, 'membership_status' => MembershipStatusEnum::ACTIVE,
            'joined_at' => now(), 'email_verified_at' => now()])->save();
        $actor->assignRole(Role::createWithPermissions('team-reader', ['shop.users.view' => 7]));
        $member = TenantUser::factory()->create();
        $request = Request::create('/');
        $request->setUserResolver(fn () => $actor);
        expect(fn () => app(UserRoleController::class)->show($request, $member))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(UserPermissionController::class)->show($request, $member))
            ->toThrow(AuthorizationException::class);
    });
});
