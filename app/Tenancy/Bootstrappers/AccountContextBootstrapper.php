<?php

namespace App\Tenancy\Bootstrappers;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\SessionManager;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class AccountContextBootstrapper implements TenancyBootstrapper
{
    /** @var array<string, mixed> */
    private array $original = [];

    public function __construct(private Repository $config, private AuthManager $auth, private SessionManager $sessions, private Application $app) {}

    public function bootstrap(Tenant $tenant): void
    {
        foreach (['auth.defaults.guard', 'auth.defaults.passwords', 'session.cookie', 'session.domain', 'session.connection'] as $key) {
            $this->original[$key] = $this->config->get($key);
        }

        $this->config->set([
            'auth.defaults.guard' => 'tenant',
            'auth.defaults.passwords' => 'tenant',
            'session.cookie' => 'aydra_tenant_'.str_replace('-', '', (string) $tenant->getTenantKey()),
            'session.domain' => null,
            'session.connection' => 'tenant',
        ]);
        $this->auth->forgetGuards();
        $this->sessions->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }

    public function revert(): void
    {
        $this->config->set($this->original);
        $this->auth->forgetGuards();
        $this->sessions->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->original = [];
    }
}
