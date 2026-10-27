<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Users\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class AuthorizationMutation
{
    private const int ATTEMPTS = 3;

    public function __construct(private PermissionRegistrar $permissions) {}

    /**
     * @template TTarget
     * @template TResult
     *
     * @param  Closure(): TTarget  $loadTarget
     * @param  Closure(TTarget): TResult  $mutation
     * @return TResult
     */
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

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $mutation
     * @return TResult
     */
    public function transaction(Closure $mutation): mixed
    {
        try {
            return DB::transaction($mutation, self::ATTEMPTS);
        } catch (Throwable $exception) {
            $this->permissions->forgetCachedPermissions();

            throw $exception;
        }

    }
}
