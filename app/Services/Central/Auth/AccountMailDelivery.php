<?php

namespace App\Services\Central\Auth;

use App\Models\Central\Users\User;
use App\Events\Central\Auth\AccountInvitationRequested;
use App\Events\Central\Auth\StaffInvitationResent;
use App\Services\AccountMailEvents;
use Closure;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\ExceptionInterface as MailerException;

class AccountMailDelivery
{
    public function invitation(User $user, string $token, ?string $actorUuid = null, bool $staffResend = false): bool
    {
        return AccountMailEvents::dispatch('central_invitation', function () use ($user, $token, $actorUuid, $staffResend): object {
            $url = rtrim(config('app.url'), '/').route('password.reset', [
                'token' => $token, 'email' => $user->email,
            ], absolute: false);
            $expiresAt = now()->addMinutes((int) config('auth.passwords.central.expire'))->getTimestamp();

            return $staffResend
                ? new StaffInvitationResent($user->uuid, $user->email, $token, $url, $expiresAt, $actorUuid)
                : new AccountInvitationRequested($user->uuid, $user->email, $token, $url, $expiresAt, $actorUuid);
        });
    }

    public function verification(User $user): bool
    {
        return $this->deliver('verification', $user, fn () => $user->sendEmailVerificationNotification());
    }

    private function deliver(string $operation, User $user, Closure $send): bool
    {
        try {
            $send();

            return true;
        } catch (MailerException $exception) {
            Log::warning('Central account email delivery failed.', [
                'operation' => $operation,
                'user_uuid' => $user->uuid,
                'exception_class' => $exception::class,
            ]);

            return false;
        }
    }
}
