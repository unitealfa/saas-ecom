<?php

namespace App\Notifications\Central\Auth;

use App\Models\Central\UserContact;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyContactEmail extends Notification
{
    public function __construct(public UserContact $contact) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Vérifiez votre adresse e-mail Aydra')
            ->line('Confirmez cette adresse pour pouvoir l’utiliser avec votre compte Aydra.')
            ->action('Vérifier mon adresse', URL::temporarySignedRoute('central.contacts.verify', now()->addMinutes(60), [
                'contact' => $this->contact->uuid, 'hash' => sha1($this->contact->value),
            ]))
            ->line('Connectez-vous au compte qui a ajouté cette adresse. Ce lien expire dans une heure.');
    }
}
