<?php

namespace App\Listeners\Tenant\Users;

use App\Events\Tenant\Users\WelcomeEmployeeMailRequested;
use App\Models\Tenant\Users\User;
use App\Notifications\Tenant\WelcomeEmployeeNotification;
use App\Services\AccountMailEvents;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;
use Throwable;

final class SendWelcomeEmployee implements ShouldQueue, ShouldBeEncrypted
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

    public function send(WelcomeEmployeeMailRequested $event): void
    {
        try {
            AccountMailEvents::assertTenant($event->tenantUuid);
            $user = User::where('uuid', $event->userUuid)->where('email', $event->email)->first();
            if ($user !== null) {
                $user->notifyNow(new WelcomeEmployeeNotification($event->shopName, $event->url));
            }
        } catch (Throwable) {
            throw new RuntimeException('Account mail delivery failed.');
        }
    }

    public function failed(WelcomeEmployeeMailRequested $event, Throwable $exception): void
    {
        AccountMailEvents::logFailure('tenant_welcome', $exception);
    }
}
