<?php

namespace App\Listeners\Central\Auth;

use App\Events\Central\Auth\AccountInvitationRequested;
use App\Events\Central\Auth\StaffInvitationResent;
use App\Models\Central\Users\User;
use App\Notifications\Central\Auth\AccountInvitation;
use App\Services\AccountMailEvents;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;
use Throwable;

final class SendAccountInvitation implements ShouldQueue, ShouldBeEncrypted
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

    public function send(AccountInvitationRequested|StaffInvitationResent $event): void
    {
        try {
            if (tenancy()->initialized) {
                throw new RuntimeException('Unexpected tenant context.');
            }
            $user = User::where('uuid', $event->userUuid)->where('email', $event->email)->first();
            if ($user === null || now()->getTimestamp() >= $event->expiresAt) {
                return;
            }
            $user->notifyNow(new AccountInvitation($event->token, $event->url));
        } catch (Throwable) {
            throw new RuntimeException('Account mail delivery failed.');
        }
    }

    public function failed(AccountInvitationRequested|StaffInvitationResent $event, Throwable $exception): void
    {
        AccountMailEvents::invitationFailed('central_invitation', $exception, $event->actorUuid);
    }
}
