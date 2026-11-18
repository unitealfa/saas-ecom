<?php

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Users\User as TenantUser;
use App\Models\User as CentralUser;
use App\Services\Tenant\Authorization\TenantAuthorizationMode;
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
    $first->run(function (): void { Role::where('name', 'manager-test')->firstOrFail()->update(['label' => 'Différent']); });
    $second->run(function (): void { expect(Role::where('name', 'manager-test')->firstOrFail()->label)->not->toBe('Différent'); });
});
