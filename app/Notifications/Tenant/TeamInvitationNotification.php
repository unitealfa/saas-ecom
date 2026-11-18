<?php

namespace App\Notifications\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TeamInvitationNotification extends Notification
{
    use Queueable;
    public function __construct(private readonly string $url, private readonly string $shopName) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Invitation à rejoindre '.$this->shopName)
            ->line('Vous êtes invité à rejoindre l’équipe de '.$this->shopName.'.')
            ->action('Accepter mon invitation', $this->url)
            ->line('Ce lien est personnel, temporaire et ne doit pas être partagé.');
    }
}
