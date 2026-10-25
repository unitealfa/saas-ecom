<?php

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Country;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;

test('central administrators without permissions receive 403 on administrative endpoints', function (string $method, string $routeName): void {
    $actor = User::factory()->create();
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING]);
    $staff = User::factory()->create();
    Permission::findOrCreate('saas.users.view', 'central');
    $role = Role::createWithPermissions('audit-staff', ['saas.users.view' => 9999]);
    $staff->assignRole($role);
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create(['user_id' => $merchant->id]);
    $parameters = [
        'user' => str_starts_with($routeName, 'central.staff.') ? $staff : $merchant,
        'role' => $role,
        'country' => Country::findOrFail($actor->country_id),
        'tenant' => $tenant,
    ];
    $route = app('router')->getRoutes()->getByName($routeName);
    expect($route)->not->toBeNull();
    $parameters = array_intersect_key($parameters, array_flip($route->parameterNames()));

    $this->actingAs($actor, 'central')->json($method, route($routeName, $parameters))->assertForbidden();

    expect($merchant->fresh()->status)->toBe(StatusEnum::PENDING);
    expect($staff->fresh()->trashed())->toBeFalse();
    Event::assertDispatchedTimes(TenantCreated::class, 1);
})->with([
    ['GET', 'central.users.index'], ['GET', 'central.users.create'], ['POST', 'central.users.store'],
    ['GET', 'central.users.show'], ['GET', 'central.users.edit'], ['PUT', 'central.users.update'],
    ['DELETE', 'central.users.destroy'], ['GET', 'central.users.history'], ['PATCH', 'central.users.status'],
    ['POST', 'central.users.invitation'], ['GET', 'central.users.pending'],
    ['POST', 'central.users.approve'], ['POST', 'central.users.reject'],
    ['POST', 'central.users.legal.validate'], ['POST', 'central.users.legal.reject'],
    ['GET', 'central.staff.index'], ['GET', 'central.staff.create'], ['POST', 'central.staff.store'],
    ['GET', 'central.staff.show'], ['GET', 'central.staff.edit'], ['PUT', 'central.staff.update'],
    ['DELETE', 'central.staff.destroy'], ['PATCH', 'central.staff.status'], ['POST', 'central.staff.invitation'],
    ['GET', 'central.staff.roles.edit'], ['PUT', 'central.staff.roles.update'], ['POST', 'central.staff.roles.renew'],
    ['GET', 'central.staff.permissions.index'], ['PUT', 'central.staff.permissions.update'],
    ['GET', 'central.roles.index'], ['GET', 'central.roles.create'], ['POST', 'central.roles.store'],
    ['GET', 'central.roles.show'], ['GET', 'central.roles.edit'], ['PUT', 'central.roles.update'], ['DELETE', 'central.roles.destroy'],
    ['GET', 'central.permissions.index'],
    ['GET', 'central.countries.index'], ['GET', 'central.countries.create'], ['POST', 'central.countries.store'],
    ['GET', 'central.countries.edit'], ['PUT', 'central.countries.update'],
    ['PATCH', 'central.countries.activate'], ['DELETE', 'central.countries.destroy'],
    ['GET', 'central.tenants.index'], ['GET', 'central.tenants.create'], ['GET', 'central.tenants.show'],
    ['GET', 'central.tenants.edit'], ['PUT', 'central.tenants.update'], ['GET', 'central.tenants.history'],
    ['POST', 'central.tenants.suspend'], ['POST', 'central.tenants.activate'], ['POST', 'central.tenants.archive'],
    ['PUT', 'central.tenants.priorities'], ['GET', 'central.users.tenants.create'], ['POST', 'central.users.tenants.store'],
]);

test('a central account cannot retry another merchants failed provisioning without permission', function (): void {
    $actor = User::factory()->create();
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create(['status' => App\Enums\Central\Tenants\StatusEnum::PROVISIONING_FAILED]);

    $this->actingAs($actor, 'central')->postJson(route('owner.shops.retry', $tenant))->assertForbidden();

    expect($tenant->fresh()->status)->toBe(App\Enums\Central\Tenants\StatusEnum::PROVISIONING_FAILED);
    Event::assertDispatchedTimes(TenantCreated::class, 1);
});

test('personnel permission consultation does not require the modification permission', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $role = Role::createWithPermissions('permission-observer', ['saas.staff.permissions.view' => 9999]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    $target = User::factory()->create();
    $target->assignRole(Role::findByName('user-reader', 'central'));

    $this->actingAs($actor, 'central')->get(route('central.staff.permissions.index', $target))->assertOk();

    expect($actor->can('saas.staff.permissions.update'))->toBeFalse();
});

test('SaaS personnel with a retained profile and no effective permissions receive 403 on every admin route', function (): void {
    Permission::findOrCreate('saas.users.view', 'central');
    $role = Role::createWithPermissions('revoked-staff-profile', ['saas.users.view' => 9999]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    $actor->revokePermissionTo('saas.users.view');
    $merchant = User::factory()->create(['status' => StatusEnum::PENDING]);
    $staff = User::factory()->create();
    $staff->assignRole($role);
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create(['user_id' => $merchant->id]);
    $parameters = ['user' => $merchant, 'role' => $role, 'tenant' => $tenant,
        'country' => Country::findOrFail($actor->country_id)];
    $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route): bool => $route->getDomain() === parse_url(config('app.url'), PHP_URL_HOST)
        && str_starts_with($route->uri(), 'admin/'));
    expect($actor->hasRole($role))->toBeTrue();
    expect($actor->getAllPermissions())->toBeEmpty();
    expect($routes)->not->toBeEmpty();
    $this->actingAs($actor, 'central');

    foreach ($routes as $route) {
        $arguments = [...$parameters, 'user' => str_starts_with($route->getName(), 'central.staff.') ? $staff : $merchant];
        $arguments = array_intersect_key($arguments, array_flip($route->parameterNames()));
        $this->json($route->methods()[0], route($route->getName(), $arguments), [
            'status' => StatusEnum::SUSPENDED->value, 'is_active' => false,
        ])->assertForbidden();
    }

    Event::assertDispatchedTimes(TenantCreated::class, 1);
});
