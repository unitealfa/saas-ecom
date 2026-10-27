<?php

namespace App\Services\Central\Auth;

use App\Models\Central\Users\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;

class RegistrationVerificationNotification extends SendEmailVerificationNotification
{
    public const string FLASH_KEY = 'registration_email_sent';

    public function __construct(private AccountMailDelivery $mail) {}

    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            parent::handle($event);

            return;
        }

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $sent = $this->mail->verification($user);

        $request = request();
        if ($request->hasSession()) {
            $request->session()->flash(self::FLASH_KEY, $sent);
        }
    }
}
