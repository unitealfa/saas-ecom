<?php

namespace App\Providers;

use App\Http\Middleware\Tenant\EnsureTenantAvailable;
use App\Http\Middleware\Tenant\FinishTenantRequest;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Tenants\Tenant;
use App\Services\Central\Tenants\TenantDatabaseName;
use App\Services\Central\Tenants\TenantProvisioner;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LogicException;
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
     * Ordre d'exécution voulu : EnsureTenantAvailable doit répondre (404/503)
     * AVANT l'initialisation, et FinishTenantRequest doit tout entourer.
     */
    private const array MIDDLEWARE_PRIORITY = [
        FinishTenantRequest::class,
        PreventAccessFromCentralDomains::class,
        EnsureTenantAvailable::class,
        InitializeTenancyByDomain::class,
    ];

    public function boot(): void
    {
        DatabaseConfig::generateDatabaseNamesUsing($this->app->make(TenantDatabaseName::class));

        Event::listen(TenancyInitialized::class, BootstrapTenancy::class);
        Event::listen(TenancyEnded::class, RevertToCentralContext::class);
        Event::listen(TenantCreated::class, $this->onTenantCreated(...));

        $this->app->booted(fn () => Route::group([], base_path('routes/tenant.php')));

        $this->prioritizeMiddleware();
    }

    private function onTenantCreated(TenantCreated $event): void
    {
        /** @var Tenant $tenant */
        $tenant = $event->tenant;
        $reservation = $tenant->fresh() ?? throw new LogicException('Tenant reservation is missing.');

        if ($reservation->getInternal('db_name') === null) {
            $reservation->setInternal('db_name', $tenant->database()->getName());
            $reservation->save();
        }

        $tenant->setInternal('db_name', $reservation->getInternal('db_name'));

        // Le provisionnement ne démarre qu'une fois la transaction validée, et une seule fois.
        DB::connection(config('tenancy.database.central_connection'))->afterCommit(function () use ($tenant): void {
            $alreadyDeployed = TenantSchemaDeployment::where('tenant_id', $tenant->id)
                ->where('operation', 1)
                ->exists();

            if (! $alreadyDeployed) {
                $this->app->make(TenantProvisioner::class)->provision(Tenant::query()->findOrFail($tenant->id));
            }
        });
    }

    private function prioritizeMiddleware(): void
    {
        /** @var Kernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);

        // prepend insère en tête : on parcourt à l'envers pour obtenir l'ordre de la liste.
        foreach (array_reverse(self::MIDDLEWARE_PRIORITY) as $middleware) {
            $kernel->prependToMiddlewarePriority($middleware);
        }
    }
}
