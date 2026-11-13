<?php

namespace App\Http\Controllers;

use App\Models\Central\Users\User;
use App\Models\Tenant\Users\User as TenantUser;

abstract class Controller
{
    protected function tenantActor(): TenantUser
    {
        $actor = auth('tenant')->user();
        abort_unless(tenancy()->initialized && $actor instanceof TenantUser, 403);

        return $actor;
    }

    protected function centralActor(): User
    {
        $actor = auth('central')->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
