<?php

namespace App\Services\Central\Auth;

use App\Models\Central\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;

class RegistrationVerificationNotification extends SendEmailVerificationNotification
{
    public function __construct(private AccountMailDelivery $mail) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            parent::handle($event);

            return;
        }
        if (! $event->user->hasVerifiedEmail()) {
            $sent = $this->mail->verification($event->user);
            if (request()->hasSession()) {
                request()->session()->flash('registration_email_sent', $sent);
            }
        }
    }
}
