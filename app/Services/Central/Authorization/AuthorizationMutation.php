<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Role;
use App\Models\Central\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class AuthorizationMutation
{
    public function __construct(private PermissionRegistrar $permissions) {}

    /**
     * @template TTarget
     * @template TResult
     * @param Closure(): TTarget $loadTarget
     * @param Closure(TTarget): TResult $mutation
     * @return TResult
     */
    public function run(
        User $actor,
        string $ability,
        Closure $loadTarget,
        Closure $mutation,
        bool $refreshPermissionCache = false,
    ): mixed {
        return $this->transaction(function () use ($actor, $ability, $loadTarget, $mutation): mixed {
            $target = $loadTarget();
            Gate::forUser($actor)->authorize($ability, $target);

            return $mutation($target);
        }, $refreshPermissionCache);
    }

    /**
     * Synchronization only; background or compound operations provide their own policy checks.
     *
     * @template T
     * @param  Closure(): T  $mutation
     * @return T
     */
    public function transaction(Closure $mutation, bool $refreshPermissionCache = false): mixed
    {
        if (tenancy()->initialized) {
            throw new LogicException('Central authorization mutations require the central context.');
        }

        try {
            return DB::transaction(function () use ($mutation, $refreshPermissionCache): mixed {
                $this->acquireAuthorizationLock();
                $result = $mutation();
                if ($refreshPermissionCache) {
                    DB::afterCommit(fn () => $this->permissions->forgetCachedPermissions());
                }

                return $result;
            }, 3);
        } catch (Throwable $exception) {
            // Native package operations can fill the registrar before a transaction is rolled back.
            $this->permissions->forgetCachedPermissions();
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['conflict' => 'Ce nom, cet e-mail ou cette composition existe déjà.']);
            }
            throw $exception;
        }
    }

    private function acquireAuthorizationLock(): void
    {
        // Toutes les mutations centrales prennent le même verrou avant de vérifier les droits.
        // La ligne du rôle Super Admin sert de verrou commun, sans être modifiée.
        // Une opération concurrente attend le commit ou le rollback de la transaction.
        // Les autorisations de l'Actor sont ensuite vérifiées par Gate et les Policies.
        Role::forCentralGuard()
            ->where('is_super_admin', true)
            ->where('is_system', true)
            ->where('is_protected', true)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
