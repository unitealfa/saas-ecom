<?php

namespace App\Services\Tenant\Users;

use App\Models\Tenant\Users\User;
use App\Notifications\Tenant\ConfirmEmployeeEmailNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class TenantEmailChangeService
{
    public function __construct(private readonly TenantInvitationUrl $urls) {}

    public function send(User $member, string $email): void
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            throw ValidationException::withMessages(['email' => 'L’envoi des e-mails est indisponible. Configurez le transport e-mail.']);
        }
        $path = URL::temporarySignedRoute('tenant.settings.email.confirm', now()->addMinutes(30), [
            'user' => $member->uuid,
            'tenant' => (string) tenant()->getTenantKey(),
            'email' => Crypt::encryptString($email),
            'previous' => hash('sha256', $member->email),
        ], absolute: false);
        $settingsPath = route('tenant.settings.show', absolute: false);
        $url = $this->urls->make('tenant.settings.show');
        try {
            Notification::route('mail', $email)->notify(new ConfirmEmployeeEmailNotification(
                substr($url, 0, -strlen($settingsPath)).$path,
            ));
        } catch (TransportExceptionInterface) {
            throw ValidationException::withMessages(['email' => 'Impossible d’envoyer le lien de confirmation. Réessayez.']);
        }
    }
}
