<?php

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Users\StatusEnum;
use App\Models\Central\Activity as CentralActivity;
use App\Models\Central\Permission as CentralPermission;
use App\Models\Central\Role as CentralRole;
use App\Models\Central\Tenant;
use App\Models\Tenant\Activity as TenantActivity;
use App\Models\Tenant\Permission as TenantPermission;
use App\Models\Tenant\Role as TenantRole;
use App\Models\Tenant\User as ShopUser;
use App\Models\User;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Support\CauserResolver;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\PermissionRegistrar;
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

test('Spatie permissions keep the configured durations and use Laravel Gate', function (): void {
    $this->freezeTime();
    $user = User::factory()->create();
    $permission = CentralPermission::create(['name' => 'saas.orders.review', 'guard_name' => 'central']);
    $role = CentralRole::create(['name' => 'reviewer', 'guard_name' => 'central']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    expect((int) $role->permissions()->firstOrFail()->pivot->duration_days)->toBe(9999);
    expect($user->can('saas.orders.review'))->toBeTrue();
    $role->setPermissionDuration('saas.orders.review', 7);
    $assignedAt = $user->roles()->firstOrFail()->pivot->assigned_at;
    $this->travel(1)->days();
    $user->syncRoles($role);
    $role->syncPermissions($permission);
    expect($user->roles()->firstOrFail()->pivot->assigned_at)->toEqual($assignedAt);
    expect((int) $role->permissions()->firstOrFail()->pivot->duration_days)->toBe(7);
    $this->travel(8)->days();
    expect($user->can('saas.orders.review'))->toBeFalse();

    $direct = CentralPermission::create(['name' => 'saas.payments.review', 'guard_name' => 'central']);
    $user->givePermissionTo($direct);
    $expiresAt = $user->permissions()->firstOrFail()->pivot->expires_at;
    $this->travel(1)->days();
    $user->syncPermissions($direct);
    expect($user->permissions()->firstOrFail()->pivot->expires_at)->toEqual($expiresAt);
    expect($user->can('saas.payments.review'))->toBeTrue();
    $this->travel(9999)->days();
    expect($user->can('saas.payments.review'))->toBeFalse();
});

test('Spatie refuses duplicate role names and identical permission compositions', function (): void {
    $permission = CentralPermission::create(['name' => 'saas.orders.review', 'guard_name' => 'central']);
    $role = CentralRole::create(['name' => 'reviewer', 'guard_name' => 'central']);
    $role->givePermissionTo($permission);
    expect(fn () => CentralRole::create(['name' => 'reviewer', 'guard_name' => 'central']))->toThrow(RoleAlreadyExists::class);
    $other = CentralRole::create(['name' => 'other-reviewer', 'guard_name' => 'central']);
    expect(fn () => $other->givePermissionTo($permission))->toThrow(QueryException::class);
    expect($other->permissions()->count())->toBe(0);
    expect(fn () => $role->setPermissionDuration('saas.orders.review', 10000))->toThrow(InvalidArgumentException::class);
    $user = User::factory()->create();
    $user->assignRole($role);
    expect(fn () => $user->givePermissionTo($permission))->toThrow(LogicException::class);
    $other->permissions()->attach($permission, ['duration_days' => 3]);
    expect(fn () => $user->assignRole($other))->toThrow(LogicException::class);
});

test('roles are created with their complete composition and guarded metadata', function (): void {
    $permission = CentralPermission::create(['name' => 'saas.catalog.manage']);
    $root = new CentralRole(['name' => 'root']);
    $root->forceFill(['is_system' => true, 'is_protected' => true, 'is_super_admin' => true])->save();
    $role = CentralRole::createWithPermissions('catalog-manager', ['saas.catalog.manage' => 9999]);
    expect($role->permission_signature)->toBe(hash('sha256', json_encode([[$permission->id, 9999]])));
    expect(fn () => CentralRole::createWithPermissions('duplicate', ['saas.catalog.manage' => 9999]))->toThrow(QueryException::class);
    expect(CentralRole::where('name', 'duplicate')->exists())->toBeFalse();
    $role->fill(['guard_name' => 'tenant', 'is_super_admin' => true, 'permission_signature' => 'forged'])->save();
    expect($role->fresh()->guard_name)->toBe('central');
    expect((bool) $role->fresh()->is_super_admin)->toBeFalse();
    expect($role->fresh()->permission_signature)->not->toBe('forged');
    $user = User::factory()->create();
    $user->assignRole($root);
    expect($user->can('saas.catalog.manage'))->toBeTrue();
    expect(fn () => $user->givePermissionTo($permission))->toThrow(LogicException::class);
});

test('changing an assigned role cannot introduce a permission shared with another role', function (): void {
    CentralPermission::create(['name' => 'saas.orders.review']);
    $catalog = CentralPermission::create(['name' => 'saas.catalog.manage']);
    $orders = CentralRole::createWithPermissions('orders', ['saas.orders.review' => 9999]);
    $products = CentralRole::createWithPermissions('products', ['saas.catalog.manage' => 9999]);
    $user = User::factory()->create();
    $user->assignRole($orders, $products);

    expect(fn () => $orders->givePermissionTo($catalog))->toThrow(LogicException::class);
    expect($orders->permissions()->count())->toBe(1);
    expect($user->roles()->count())->toBe(2);
});

test('Spatie caches permissions and records activity in the correct MySQL database', function (): void {
    $owner = User::factory()->create();
    $centralLog = activity()->causedBy($owner)->log('Central marker');
    expect($centralLog)->toBeInstanceOf(CentralActivity::class);
    expect($centralLog->uuid)->toBeUuid();
    expect($centralLog->correlation_id)->toBeUuid();
    app(CauserResolver::class)->setCauser($owner);
    $first = Tenant::factory()->create(['user_id' => $owner->id, 'slug' => 'shop-a']);
    $second = Tenant::factory()->create(['user_id' => $owner->id, 'slug' => 'shop-b']);

    $first->run(function (): void {
        $user = ShopUser::firstOrFail();
        $user->forceFill(['membership_status' => 1, 'joined_at' => now()])->save();
        $permission = TenantPermission::create(['name' => 'products.manage', 'guard_name' => 'tenant']);
        $role = TenantRole::create(['name' => 'manager', 'guard_name' => 'tenant']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);
        expect($user->getAttribute('status'))->toBe(StatusEnum::ACTIVE);
        expect($user->getAttribute('membership_status'))->toBe(MembershipStatusEnum::ACTIVE);
        expect($user->roles()->count())->toBe(1);
        expect($role->permissions()->count())->toBe(1);
        expect($user->hasPermissionTo('products.manage'))->toBeTrue();
        expect($user->can('products.manage'))->toBeTrue();
        expect(app(CauserResolver::class)->resolve())->toBeNull();
        expect(app(PermissionRegistrar::class)->getPermissionClass())->toBe(TenantPermission::class);
        $log = activity()->causedBy($user)->log('Shop A marker');
        expect($log)->toBeInstanceOf(TenantActivity::class);
        expect($log->causer->getKey())->toBe($user->id);
    });
    $second->run(function (): void {
        $user = ShopUser::firstOrFail();
        $user->forceFill(['membership_status' => 1, 'joined_at' => now()])->save();
        expect($user->can('products.manage'))->toBeFalse();
        expect(TenantActivity::where('description', 'Shop A marker')->exists())->toBeFalse();
        activity()->causedBy($user)->log('Shop B marker');
    });
    expect(app(PermissionRegistrar::class)->getPermissionClass())->toBe(CentralPermission::class);
    expect(CentralActivity::whereIn('description', ['Shop A marker', 'Shop B marker'])->count())->toBe(0);
    expect(CentralActivity::where('description', 'Central marker')->count())->toBe(1);
    expect(CentralActivity::get()->toJson())->not->toContain('password', $owner->password);
});
