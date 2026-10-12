<?php

namespace App\Services\Central\Auth;

use App\Models\Central\User;
use App\Notifications\Central\Auth\AccountInvitation;
use Closure;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AccountMailDelivery
{
    public function invitation(User $user, string $token): bool
    {
        return $this->deliver($user, fn () => $user->notifyNow(new AccountInvitation($token)));
    }

    public function verification(User $user): bool
    {
        return $this->deliver($user, fn () => $user->sendEmailVerificationNotification());
    }

    private function deliver(User $user, Closure $send): bool
    {
        try {
            $send();

            return true;
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Central account email delivery failed.', [
                'user_uuid' => $user->uuid, 'exception_class' => $exception::class,
            ]);

            return false;
        }
    }
}
