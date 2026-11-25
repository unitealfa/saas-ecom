<?php

namespace App\Notifications\Central\Auth;

use Illuminate\Notifications\Notification;

final class AccountMailFailureNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'account_invitation_delivery_failed',
            'message' => 'Un e-mail d’invitation n’a pas pu être transmis. Vérifiez l’invitation et demandez un nouvel envoi si nécessaire.',
        ];
    }
}
