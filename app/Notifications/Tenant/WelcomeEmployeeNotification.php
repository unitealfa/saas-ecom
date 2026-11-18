<?php

namespace App\Notifications\Tenant;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WelcomeEmployeeNotification extends Notification
{
    public function __construct(private readonly string $shopName, private readonly string $url) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Bienvenue dans '.$this->shopName)
            ->line('Votre accès à '.$this->shopName.' est activé.')
            ->action('Ouvrir ma boutique', $this->url);
    }
}
