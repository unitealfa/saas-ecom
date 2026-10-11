<?php

namespace App\Providers;

use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Policies\Central\RolePolicy;
use App\Policies\Central\TenantPolicy;
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
            'shop_user' => \App\Models\Tenant\User::class,
        ]);
        $this->loadMigrationsFrom(database_path('migrations/central'));
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(\App\Models\Central\User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
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
