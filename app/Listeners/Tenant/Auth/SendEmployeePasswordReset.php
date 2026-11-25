<?php

namespace App\Listeners\Tenant\Auth;

use App\Events\Tenant\Auth\EmployeePasswordResetRequested;
use App\Models\Tenant\Users\User;
use App\Notifications\Tenant\ResetEmployeePasswordNotification;
use App\Services\AccountMailEvents;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;
use Throwable;

final class SendEmployeePasswordReset implements ShouldQueue, ShouldBeEncrypted
{
    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $timeout = 60;

    public function viaConnection(): string
    {
        return config('queue.account_mail_connection', 'sync');
    }

    public function viaQueue(): string
    {
        return 'default';
    }

    public function send(EmployeePasswordResetRequested $event): void
    {
        try {
            AccountMailEvents::assertTenant($event->tenantUuid);
            $user = User::where('uuid', $event->userUuid)->where('email', $event->email)->first();
            if ($user !== null && now()->getTimestamp() < $event->expiresAt) {
                $user->notifyNow(new ResetEmployeePasswordNotification($event->url));
            }
        } catch (Throwable) {
            throw new RuntimeException('Account mail delivery failed.');
        }
    }

    public function failed(EmployeePasswordResetRequested $event, Throwable $exception): void
    {
        AccountMailEvents::logFailure('tenant_password_reset', $exception);
    }
}
