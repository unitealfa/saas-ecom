<?php

namespace App\Notifications\Tenant;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ResetEmployeePasswordNotification extends Notification
{
    public function __construct(private readonly string $url) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Réinitialisation du mot de passe boutique')
            ->line('Une réinitialisation a été demandée pour votre compte de boutique.')
            ->action('Réinitialiser mon mot de passe', $this->url)
            ->line('Ignorez ce message si vous n’êtes pas à l’origine de cette demande.');
    }
}
