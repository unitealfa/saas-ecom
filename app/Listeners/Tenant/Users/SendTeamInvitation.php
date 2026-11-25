<?php

namespace App\Listeners\Tenant\Users;

use App\Events\Tenant\Users\TeamInvitationMailRequested;
use App\Events\Tenant\Users\TeamInvitationMailResent;
use App\Notifications\Tenant\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;
use App\Services\AccountMailEvents;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;
use Throwable;

final class SendTeamInvitation implements ShouldQueue, ShouldBeEncrypted
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

    public function send(TeamInvitationMailRequested|TeamInvitationMailResent $event): void
    {
        try {
            AccountMailEvents::assertTenant($event->tenantUuid);
            if (now()->getTimestamp() >= $event->expiresAt) {
                return;
            }
            Notification::route('mail', $event->email)->notifyNow(new TeamInvitationNotification($event->url, $event->shopName));
        } catch (Throwable) {
            throw new RuntimeException('Account mail delivery failed.');
        }
    }

    public function failed(TeamInvitationMailRequested|TeamInvitationMailResent $event, Throwable $exception): void
    {
        AccountMailEvents::invitationFailed('tenant_invitation', $exception, $event->actorUuid, $event->ownerUuid);
    }
}
