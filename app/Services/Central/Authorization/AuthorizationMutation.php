<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class AuthorizationMutation
{
    public function __construct(private PermissionRegistrar $permissions) {}


    public function run(
        User $actor,
        string $ability,
        Closure $loadTarget,
        Closure $mutation,
    ): mixed {
        return $this->transaction(function () use ($actor, $ability, $loadTarget, $mutation): mixed {
            $target = $loadTarget();
            Gate::forUser($actor)->authorize($ability, $target);

            return $mutation($target);
        });
    }

    
    public function transaction(Closure $mutation): mixed
    {
        try {
            return DB::transaction($mutation, 3);
        } catch (Throwable $exception) {
            $this->permissions->forgetCachedPermissions();
            throw $exception;
        }
    }
}
