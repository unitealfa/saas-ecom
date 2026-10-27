<?php

declare(strict_types=1);

use App\Models\Central\Audit\Activity;
use App\Models\Central\Countries\Country;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Domains\Domain;
use App\Models\Central\Features\Feature;
use App\Models\Central\Media\Media;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Models\Examples\Test;
use App\Models\Shared\Authorization\PermissionAssignment;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Users\User as ShopUser;
use App\Policies\Central\CountryPolicy;
use App\Policies\Central\DomainPolicy;
use App\Policies\Central\FeaturePolicy;
use App\Policies\Central\PermissionPolicy;
use App\Policies\Central\RolePolicy;
use App\Policies\Central\TenantPolicy;
use App\Policies\Central\TenantSchemaDeploymentPolicy;
use App\Policies\Central\UserPolicy;
use App\Tenancy\Bootstrappers\SpatieContextBootstrapper;
use Database\Factories\Central\DomainFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    config(['database.connections.tenant' => config('database.connections.mysql')]);
    foreach (['mysql', 'tenant'] as $connection) {
        DB::connection($connection)->beforeExecuting(static function (): never {
            throw new LogicException('Model organization checks must not execute SQL.');
        });
    }
});

test('organized models preserve their tables connections and persisted morph types', function (string $class, string $table, string $connection, string $morph): void {
    $original = DB::getDefaultConnection();
    DB::setDefaultConnection('tenant');
    try {
        /** @var Model $model */
        $model = new $class;
        expect($model->getTable())->toBe($table);
        expect($model->getConnectionName())->toBe($connection);
        expect($model->getMorphClass())->toBe($morph);
        expect(Relation::getMorphedModel($morph))->toBe($class);
    } finally {
        DB::setDefaultConnection($original);
    }
})->with([
    [Country::class, 'countries', 'mysql', 'App\\Models\\Central\\Country'],
    [Domain::class, 'domains', 'mysql', 'App\\Models\\Central\\Domain'],
    [Feature::class, 'features', 'mysql', 'App\\Models\\Central\\Feature'],
    [Plan::class, 'plans', 'mysql', 'App\\Models\\Central\\Plan'],
    [PlanFeature::class, 'plan_features', 'mysql', 'App\\Models\\Central\\PlanFeature'],
    [Subscription::class, 'subscriptions', 'mysql', 'App\\Models\\Central\\Subscription'],
    [Tenant::class, 'tenants', 'mysql', 'App\\Models\\Central\\Tenant'],
    [TenantSchemaDeployment::class, 'tenant_schema_deployments', 'mysql', 'App\\Models\\Central\\TenantSchemaDeployment'],
    [Role::class, 'roles', 'mysql', 'App\\Models\\Central\\Role'],
    [Permission::class, 'permissions', 'mysql', 'App\\Models\\Central\\Permission'],
    [Media::class, 'media', 'mysql', 'App\\Models\\Central\\Media'],
    [Activity::class, 'activity_log', 'mysql', 'App\\Models\\Central\\Activity'],
    [App\Models\Tenant\Roles\Role::class, 'roles', 'tenant', 'App\\Models\\Tenant\\Role'],
    [App\Models\Tenant\Permissions\Permission::class, 'permissions', 'tenant', 'App\\Models\\Tenant\\Permission'],
    [App\Models\Tenant\Media\Media::class, 'media', 'tenant', 'App\\Models\\Tenant\\Media'],
    [App\Models\Tenant\Audit\Activity::class, 'activity_log', 'tenant', 'App\\Models\\Tenant\\Activity'],
    [Shop::class, 'shop', 'tenant', 'App\\Models\\Tenant\\Shop'],
]);

test('compatibility aliases retain their parent factories and policies', function (): void {
    expect(new App\Models\User)->toBeInstanceOf(User::class);
    expect(new App\Models\Tenant)->toBeInstanceOf(Tenant::class);
    expect(App\Models\User::factory()->modelName())->toBe(App\Models\User::class);
    expect(App\Models\Tenant::factory()->modelName())->toBe(Tenant::class);
    expect(Gate::getPolicyFor(App\Models\User::class))->toBeInstanceOf(UserPolicy::class);
    expect(Gate::getPolicyFor(App\Models\Tenant::class))->toBeInstanceOf(TenantPolicy::class);
    expect((new App\Models\User)->getMorphClass())->toBe('central_user');
    expect((new ShopUser)->getMorphClass())->toBe('shop_user');
});

test('organized models still resolve their existing factories', function (string $model): void {
    expect($model::factory()->modelName())->toBe($model);
})->with([
    Country::class, Feature::class, Plan::class, PlanFeature::class, Subscription::class,
    TenantSchemaDeployment::class, Tenant::class, Shop::class, ShopUser::class,
]);

test('the explicit domain factory resolves the organized domain model', function (): void {
    expect(DomainFactory::new()->modelName())->toBe(Domain::class);
});

test('organized model policies remain explicitly registered', function (string $model, string $policy): void {
    expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
})->with([
    [User::class, UserPolicy::class], [Tenant::class, TenantPolicy::class],
    [Country::class, CountryPolicy::class], [Domain::class, DomainPolicy::class],
    [Feature::class, FeaturePolicy::class],
    [Role::class, RolePolicy::class],
    [Permission::class, PermissionPolicy::class],
    [TenantSchemaDeployment::class, TenantSchemaDeploymentPolicy::class],
]);

test('relations reference the organized models without querying the database', function (): void {
    expect((new User)->country()->getRelated())->toBeInstanceOf(Country::class);
    expect((new User)->tenants()->getRelated())->toBeInstanceOf(Tenant::class);
    expect((new User)->subscriptions()->getRelated())->toBeInstanceOf(Subscription::class);
    expect((new Tenant)->owner()->getRelated())->toBeInstanceOf(User::class);
    expect((new Tenant)->domains()->getRelated())->toBeInstanceOf(Domain::class);
    expect((new Domain)->tenant()->getRelated())->toBeInstanceOf(Tenant::class);
    expect((new Feature)->planFeatures()->getRelated())->toBeInstanceOf(PlanFeature::class);
    expect((new Subscription)->plan()->getRelated())->toBeInstanceOf(Plan::class);
    expect((new PlanFeature)->feature()->getRelated())->toBeInstanceOf(Feature::class);
    expect((new Media)->creator()->getRelated())->toBeInstanceOf(User::class);
    expect((new App\Models\Tenant\Media\Media)->creator()->getRelated())->toBeInstanceOf(ShopUser::class);
});

test('existing activity subjects resolve through the historical morph map', function (): void {
    $activity = new Activity;
    $activity->setRawAttributes(['subject_type' => 'App\\Models\\Central\\Role', 'subject_id' => 1]);
    expect($activity->subject()->getRelated())->toBeInstanceOf(Role::class);
    $activity->setRawAttributes(['subject_type' => 'central_user', 'subject_id' => 1]);
    expect($activity->subject()->getRelated())->toBeInstanceOf(User::class);
});

test('Spatie switches model classes by context and restores the central configuration', function (): void {
    $bootstrapper = app(SpatieContextBootstrapper::class);
    $tenant = new Tenant;
    $tenant->setRawAttributes(['id' => 1]);
    $bootstrapper->bootstrap($tenant);
    try {
        expect(config('permission.models.role'))->toBe(App\Models\Tenant\Roles\Role::class);
        expect(config('permission.models.permission'))->toBe(App\Models\Tenant\Permissions\Permission::class);
        expect(config('activitylog.activity_model'))->toBe(App\Models\Tenant\Audit\Activity::class);
        expect((new App\Models\Tenant\Roles\Role)->permissions()->getRelated())->toBeInstanceOf(App\Models\Tenant\Permissions\Permission::class);
    } finally {
        $bootstrapper->revert();
    }
    expect(config('permission.models.role'))->toBe(Role::class);
    expect(config('permission.models.permission'))->toBe(Permission::class);
    expect(config('activitylog.activity_model'))->toBe(Activity::class);
});

test('the existing public login renders after the model reorganization without SQL', function (): void {
    $this->get(route('login'))->assertOk();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the shared pivot stays context neutral and the technical example retains its table', function (): void {
    $assignment = new PermissionAssignment;
    $assignment->setTable('model_has_permissions')->setConnection('tenant');
    expect($assignment->getTable())->toBe('model_has_permissions');
    expect($assignment->getConnectionName())->toBe('tenant');
    $assignment->setConnection('mysql');
    expect($assignment->getConnectionName())->toBe('mysql');
    $example = new Test;
    expect($example->getTable())->toBe('tests');
    expect($example->getConnectionName())->toBe('mysql');
});
