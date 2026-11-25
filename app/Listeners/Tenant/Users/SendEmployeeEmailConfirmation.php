<?php

namespace App\Listeners\Tenant\Users;

use App\Events\Tenant\Users\EmployeeEmailConfirmationRequested;
use App\Notifications\Tenant\ConfirmEmployeeEmailNotification;
use Illuminate\Support\Facades\Notification;
use App\Services\AccountMailEvents;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;
use Throwable;

final class SendEmployeeEmailConfirmation implements ShouldQueue, ShouldBeEncrypted
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

    public function send(EmployeeEmailConfirmationRequested $event): void
    {
        try {
            AccountMailEvents::assertTenant($event->tenantUuid);
            if (now()->getTimestamp() >= $event->expiresAt) {
                return;
            }
            Notification::route('mail', $event->email)->notifyNow(new ConfirmEmployeeEmailNotification($event->url));
        } catch (Throwable) {
            throw new RuntimeException('Account mail delivery failed.');
        }
    }

    public function failed(EmployeeEmailConfirmationRequested $event, Throwable $exception): void
    {
        AccountMailEvents::logFailure('tenant_email_confirmation', $exception);
    }
}
