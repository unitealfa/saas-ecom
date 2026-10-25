<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Throwable;


class AuthorizationMutation
{
    private const int ATTEMPTS = 3;

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
        $connection = $this->connection();

        try {
            $result = $connection->transaction($mutation, self::ATTEMPTS);
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->permissions->forgetCachedPermissions();

            throw $exception;
        }

        $connection->afterCommit(fn () => $this->permissions->forgetCachedPermissions());

        return $result;
    }

    private function connection(): Connection
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }
}