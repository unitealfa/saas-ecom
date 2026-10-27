<?php

namespace App\Providers;

use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
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
use App\Models\Examples\Test;
use App\Models\Shared\Authorization\PermissionAssignment;
use App\Models\Tenant\Shop\Shop;
use App\Models\User;
use App\Policies\Central\CountryPolicy;
use App\Policies\Central\DomainPolicy;
use App\Policies\Central\FeaturePolicy;
use App\Policies\Central\PermissionPolicy;
use App\Policies\Central\RolePolicy;
use App\Policies\Central\TenantPolicy;
use App\Policies\Central\TenantSchemaDeploymentPolicy;
use App\Policies\Central\UserPolicy;
use App\Services\SchemaBlueprint;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Blueprint::class, SchemaBlueprint::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'central_user' => User::class,
            'shop_user' => \App\Models\Tenant\Users\User::class,
            'App\\Models\\Central\\Country' => Country::class,
            'App\\Models\\Central\\Role' => Role::class,
            'App\\Models\\Central\\Permission' => Permission::class,
            'App\\Models\\Central\\Tenant' => Tenant::class,
            'App\\Models\\Central\\Domain' => Domain::class,
            'App\\Models\\Central\\TenantSchemaDeployment' => TenantSchemaDeployment::class,
            'App\\Models\\Central\\Plan' => Plan::class,
            'App\\Models\\Central\\PlanFeature' => PlanFeature::class,
            'App\\Models\\Central\\Feature' => Feature::class,
            'App\\Models\\Central\\Subscription' => Subscription::class,
            'App\\Models\\Central\\Media' => Media::class,
            'App\\Models\\Central\\Activity' => Activity::class,
            'App\\Models\\Tenant\\Role' => \App\Models\Tenant\Roles\Role::class,
            'App\\Models\\Tenant\\Permission' => \App\Models\Tenant\Permissions\Permission::class,
            'App\\Models\\Tenant\\Shop' => Shop::class,
            'App\\Models\\Tenant\\Media' => \App\Models\Tenant\Media\Media::class,
            'App\\Models\\Tenant\\Activity' => \App\Models\Tenant\Audit\Activity::class,
            'App\\Models\\PermissionAssignment' => PermissionAssignment::class,
            'App\\Models\\Test' => Test::class,
        ]);
        $this->loadMigrationsFrom(database_path('migrations/central'));
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(\App\Models\Central\Users\User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(Domain::class, DomainPolicy::class);
        Gate::policy(Feature::class, FeaturePolicy::class);
        Gate::policy(TenantSchemaDeployment::class, TenantSchemaDeploymentPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Livewire::addPersistentMiddleware([EnsureActiveCentralAccount::class]);
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()

            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
