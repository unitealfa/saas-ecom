<?php

namespace App\Notifications\Tenant;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ConfirmEmployeeEmailNotification extends Notification
{
    public function __construct(private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Confirmez votre nouvelle adresse e-mail')
            ->line('Cette demande concerne votre compte de boutique.')
            ->action('Confirmer mon adresse', $this->url)
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.');
    }
}
