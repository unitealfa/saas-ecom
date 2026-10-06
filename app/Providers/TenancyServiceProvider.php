<?php

namespace App\Providers;

use App\Http\Middleware\FinishTenantRequest;
use App\Models\Central\Tenant;
use App\Services\Central\TenantDatabaseName;
use App\Services\Central\TenantProvisioner;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Listeners\BootstrapTenancy;
use Stancl\Tenancy\Listeners\RevertToCentralContext;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        DatabaseConfig::generateDatabaseNamesUsing($this->app->make(TenantDatabaseName::class));

        Event::listen(TenancyInitialized::class, BootstrapTenancy::class);
        Event::listen(TenancyEnded::class, RevertToCentralContext::class);
        Event::listen(TenantCreated::class, function (TenantCreated $event): void {
            /** @var Tenant $tenant */
            $tenant = $event->tenant;
            $reservation = $tenant->fresh() ?? throw new \LogicException('Tenant reservation is missing.');

            if ($reservation->getInternal('db_name') === null) {
                $reservation->setInternal('db_name', $tenant->database()->getName());
                $reservation->save();
            }

            $tenant->setInternal('db_name', $reservation->getInternal('db_name'));

            DB::connection(config('tenancy.database.central_connection'))->afterCommit(
                fn () => $this->app->make(TenantProvisioner::class)->provision(Tenant::query()->findOrFail($tenant->id)),
            );
        });

        $this->app->booted(function (): void {
            Route::group([], base_path('routes/tenant.php'));
        });

        foreach (array_reverse([
            FinishTenantRequest::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByDomain::class,
        ]) as $middleware) {
            $this->app->make(Kernel::class)->prependToMiddlewarePriority($middleware);
        }
    }
}
