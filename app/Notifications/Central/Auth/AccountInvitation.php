<?php

namespace App\Notifications\Central\Auth;

use App\Models\Central\Users\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountInvitation extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token)
    {
        $this->onConnection('database')->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** @param User $notifiable */
    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.url'), '/').route('password.reset', [
            'token' => $this->token, 'email' => $notifiable->email,
        ], absolute: false);

        return (new MailMessage)->subject('Votre invitation sur Aydra')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Un compte Aydra a été créé pour vous. Choisissez votre propre mot de passe pour accepter cette invitation.')
            ->action('Choisir mon mot de passe', $url)
            ->line('Ce lien confirme également votre adresse e-mail. Vous pourrez ensuite vous connecter, si votre compte est actif.')
            ->line('Ce lien expire dans '.config('auth.passwords.central.expire').' minutes. Si vous ne souhaitez pas utiliser ce compte, ignorez ce message.');
    }
}
