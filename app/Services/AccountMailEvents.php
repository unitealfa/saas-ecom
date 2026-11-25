<?php

namespace App\Services;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User as CentralUser;
use App\Models\Tenant\Users\User as TenantUser;
use App\Notifications\Central\Auth\AccountMailFailureNotification;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

final class AccountMailEvents
{
    /** Build and dispatch in the request, only after the database transaction. */
    public static function dispatch(string $operation, Closure $build, bool $tenant = false): bool
    {
        try {
            $connection = config('queue.account_mail_connection', 'sync');
            if (! in_array($connection, ['sync', 'database'], true)
                || config('queue.connections.'.$connection.'.central')
                || ($connection === 'database' && config('queue.connections.database.connection') !== config('tenancy.database.central_connection'))
                || tenancy()->initialized !== $tenant
                || DB::connection(config('tenancy.database.central_connection'))->transactionLevel() !== 0
                || ($tenant && DB::connection('tenant')->transactionLevel() !== 0)) {
                throw new LogicException('Invalid account mail dispatch context.');
            }

            Event::dispatch($build());

            return true;
        } catch (Throwable $exception) {
            self::logFailure($operation, $exception);

            return false;
        }
    }

    public static function tenantOwnerUuid(): ?string
    {
        $tenant = tenancy()->tenant;
        $uuid = $tenant instanceof Tenant ? $tenant->owner?->uuid : null;

        return $uuid === null ? null : TenantUser::where('central_user_uuid', $uuid)->value('central_user_uuid');
    }

    public static function assertTenant(string $uuid): void
    {
        if (! tenancy()->initialized || (string) tenant()->uuid !== $uuid) {
            throw new LogicException('Account mail tenant mismatch.');
        }
    }

    public static function logFailure(string $operation, Throwable $exception): void
    {
        try {
            Log::warning('Account mail delivery failed.', [
                'operation' => $operation,
                'exception_class' => $exception::class,
            ]);
        } catch (Throwable) {
            // The failure reporter must not fail the business action or the failed hook.
        }
    }

    public static function invitationFailed(string $operation, Throwable $exception, ?string $actorUuid, ?string $ownerUuid = null): void
    {
        self::logFailure($operation, $exception);
        try {
            foreach (array_unique(array_filter([$actorUuid, $ownerUuid])) as $uuid) {
                $recipient = CentralUser::where('uuid', $uuid)->first();
                if ($recipient !== null && $recipient->status === StatusEnum::ACTIVE && $recipient->hasVerifiedEmail()) {
                    $recipient->notifyNow(new AccountMailFailureNotification);

                    return;
                }
            }
        } catch (Throwable $notificationException) {
            self::logFailure('invitation_failure_notification', $notificationException);
        }
    }
}
