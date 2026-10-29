<?php

use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
});

test('staff dashboards read their account and role membership once and never load shops', function (string $role): void {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::findByName($role, 'central'));
    $this->actingAs($user, 'central');
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->get(route('dashboard'))->assertOk()->assertSee('Consulter les utilisateurs');

    $accounts = array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'select * from `users`'));
    $memberships = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'model_has_roles'));
    $shops = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from `tenants`'));
    expect($accounts)->toHaveCount(1)
        ->and($memberships)->toHaveCount(1)
        ->and($shops)->toBeEmpty();
})->with(['root', 'user-reader']);

test('a transactional revocation cannot be bypassed by relations loaded for an earlier read', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::findByName('user-reader', 'central'));
    $permission = Permission::findByName('saas.users.view', 'central');
    $user->load(['roles', 'permissions']);
    $request = Request::create('/dashboard');
    $request->attributes->set(User::AUTHORIZATION_READ_USER, $user);
    app()->instance('request', $request);
    expect($user->hasPermissionTo($permission))->toBeTrue();

    DB::transaction(function () use ($user, $permission): void {
        $user->permissions()->detach($permission);
        expect($user->canReuseAuthorizationRelations())->toBeFalse()
            ->and($user->hasPermissionTo($permission))->toBeFalse();
    });

    app()->instance('request', Request::create('/dashboard'));
    expect($user->canReuseAuthorizationRelations())->toBeFalse()
        ->and($user->hasPermissionTo($permission))->toBeFalse();
});
