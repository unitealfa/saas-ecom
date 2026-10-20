<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Role;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Permission\PermissionRegistrar;

class AuthorizationMutation
{
    /** @template T
     * @param  Closure(): T  $mutation
     * @return T
     */
    public function run(Closure $mutation): mixed
    {
        if (tenancy()->initialized) {
            throw new LogicException('Central authorization mutations require the central context.');
        }

        $connection = (new Role)->getConnection();
        try {
            return $connection->transaction(function () use ($mutation, $connection): mixed {
                Role::forCentralGuard()->where('is_super_admin', true)
                    ->where('is_system', true)->where('is_protected', true)->lockForUpdate()->firstOrFail();
                $result = $mutation();
                $connection->afterCommit(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());

                return $result;
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages(['conflict' => 'Ce nom, cet e-mail ou cette composition existe déjà.']);
            }
            throw $exception;
        }
    }
}
