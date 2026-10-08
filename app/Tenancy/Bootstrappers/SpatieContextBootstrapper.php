<?php

namespace App\Tenancy\Bootstrappers;

use App\Models\Tenant\Activity;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Spatie\Activitylog\Support\CauserResolver;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

class SpatieContextBootstrapper implements TenancyBootstrapper
{
    /** @var array<string, mixed> */
    private array $original = [];

    public function __construct(private PermissionRegistrar $permissions) {}

    public function bootstrap(Tenant $tenant): void
    {
        foreach (['permission.models.permission', 'permission.models.role', 'permission.cache.key', 'activitylog.activity_model'] as $key) {
            $this->original[$key] = config($key);
        }
        config(['permission.cache.key' => 'spatie.permission.tenant.'.$tenant->getTenantKey(), 'activitylog.activity_model' => Activity::class]);
        $this->permissions->setPermissionClass(Permission::class)->setRoleClass(Role::class)->initializeCache();
        app()->forgetInstance(CauserResolver::class);
    }

    public function revert(): void
    {
        config($this->original);
        $this->permissions->setPermissionClass(config('permission.models.permission'))->setRoleClass(config('permission.models.role'))->initializeCache();
        app()->forgetInstance(CauserResolver::class);
    }
}
