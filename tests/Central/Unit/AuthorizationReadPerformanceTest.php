<?php

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Users\User;
use App\Models\Shared\Authorization\PermissionAssignment;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Dashboard\Dashboard;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    DB::connection()->beforeExecuting(function (): void {
        throw new LogicException('This test must not execute SQL.');
    });
});

function authorizationReader(string $method = 'GET'): User
{
    $user = new User;
    $user->setRawAttributes(['id' => 21, 'uuid' => 'reader', 'status' => StatusEnum::ACTIVE->value]);
    $request = Request::create('/dashboard', $method);
    $request->attributes->set(User::AUTHORIZATION_READ_USER, $user);
    app()->instance('request', $request);

    return $user;
}

function authorizationRole(array $attributes = []): Role
{
    $role = new Role;
    $role->forceFill(['id' => 1, 'guard_name' => 'central', 'name' => 'reader', ...$attributes]);
    $role->setRelation('pivot', new PermissionAssignment(['assigned_at' => now()->subDay()]));

    return $role;
}

test('staff dashboard does not read shops or reload roles during repeated rendering', function (): void {
    $user = authorizationReader();
    $user->setRelation('roles', new Collection([authorizationRole()]));
    $access = app(CentralUserAccess::class);

    foreach (range(1, 4) as $iteration) {
        expect($access->isStaff($user))->toBeTrue();
        $overview = app(Dashboard::class)->forUser($user);
        expect($overview['staff'])->toBeTrue()->and($overview['shops'])->toBeEmpty();
    }
});

test('loaded direct permissions retain their assignment and expiration boundaries', function (string $assigned, ?string $expires, bool $allowed): void {
    $user = authorizationReader();
    $permission = new Permission(['name' => 'saas.users.view']);
    $permission->forceFill(['id' => 4]);
    $permission->setRelation('pivot', new PermissionAssignment([
        'assigned_at' => $assigned === 'future' ? now()->addDay() : now()->subDay(),
        'expires_at' => match ($expires) {
            'future' => now()->addDay(),
            'past' => now()->subSecond(),
            default => null,
        },
    ]));
    $user->setRelation('roles', new Collection([authorizationRole()]));
    $user->setRelation('permissions', new Collection([$permission]));

    expect($user->hasPermissionTo($permission, 'central'))->toBe($allowed);
})->with([
    ['past', 'future', true], ['future', 'future', false],
    ['past', 'past', false], ['past', null, false],
]);

test('a profile does not restore a removed individual permission', function (): void {
    $user = authorizationReader();
    $user->setRelation('roles', new Collection([authorizationRole()]));
    $user->setRelation('permissions', new Collection);
    $permission = new Permission(['name' => 'saas.users.view']);
    $permission->forceFill(['id' => 4]);

    expect($user->hasPermissionTo($permission, 'central'))->toBeFalse();
});

test('root recognition keeps every protected role requirement', function (array $override, bool $allowed): void {
    $user = authorizationReader();
    $role = authorizationRole(['name' => 'root', 'is_super_admin' => true, 'is_system' => true, 'is_protected' => true, ...$override]);
    if (($override['assigned_future'] ?? false) === true) {
        $role->pivot->assigned_at = now()->addDay();
    }
    $user->setRelation('roles', new Collection([$role]));

    expect(app(CentralUserAccess::class)->isSuperAdmin($user))->toBe($allowed);
})->with([
    [[], true], [['is_system' => false], false], [['is_protected' => false], false],
    [['is_super_admin' => false], false], [['name' => 'other'], false],
    [['guard_name' => 'tenant'], false], [['assigned_future' => true], false],
]);

test('read relations are never reused for a write or another user', function (string $method): void {
    $user = authorizationReader($method);
    expect($user->canReuseAuthorizationRelations())->toBeFalse()
        ->and((new User)->canReuseAuthorizationRelations())->toBeFalse();
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

test('a transaction disables the read snapshot', function (): void {
    $user = authorizationReader();
    $connection = $user->getConnection();
    $transactions = new ReflectionProperty($connection, 'transactions');
    $transactions->setValue($connection, 1);
    try {
        expect($user->canReuseAuthorizationRelations())->toBeFalse();
    } finally {
        $transactions->setValue($connection, 0);
    }
});

test('the next request and a tenant context cannot inherit the previous read snapshot', function (): void {
    $user = authorizationReader();
    expect($user->canReuseAuthorizationRelations())->toBeTrue();
    tenancy()->initialized = true;
    try {
        expect($user->canReuseAuthorizationRelations())->toBeFalse();
    } finally {
        tenancy()->initialized = false;
    }
    app()->instance('request', Request::create('/dashboard'));
    expect($user->canReuseAuthorizationRelations())->toBeFalse();
});

test('suspended accounts cannot use their loaded direct permissions', function (): void {
    $user = authorizationReader();
    $user->forceFill(['status' => StatusEnum::SUSPENDED]);

    expect($user->hasPermissionTo(new Permission(['name' => 'saas.users.view']), 'central'))->toBeFalse()
        ->and(app(CentralUserAccess::class)->isSuperAdmin($user))->toBeFalse();
});

test('native Spatie Gate interception still delegates model abilities to the policy', function (): void {
    config(['cache.default' => 'array']);
    $user = authorizationReader();
    $permission = new Permission(['name' => 'saas.users.view']);
    $permission->forceFill(['id' => 4]);
    $permission->setRelation('pivot', new PermissionAssignment([
        'assigned_at' => now()->subDay(), 'expires_at' => now()->addDay(),
    ]));
    $user->setRelation('roles', new Collection([authorizationRole()]));
    $user->setRelation('permissions', new Collection([$permission]));
    $registrar = Mockery::mock(PermissionRegistrar::class)->makePartial();
    $registrar->__construct(app(CacheManager::class));
    $registrar->shouldReceive('getPermissions')->andReturnUsing(function (array $filters) use ($permission): Collection {
        return new Collection([$permission])->filter(function ($item) use ($filters): bool {
            foreach ($filters as $key => $value) {
                if ($item->getAttribute($key) !== $value) {
                    return false;
                }
            }

            return true;
        });
    });
    app()->instance(PermissionRegistrar::class, $registrar);

    expect(Gate::forUser($user)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($user)->allows('unregisteredAbility', User::class))->toBeFalse();

    $permission->pivot->expires_at = now()->subSecond();
    expect(Gate::forUser($user)->allows('viewAny', User::class))->toBeFalse();
});
