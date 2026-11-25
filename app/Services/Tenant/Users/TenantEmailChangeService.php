<?php

namespace App\Services\Tenant\Users;

use App\Models\Tenant\Users\User;
use App\Events\Tenant\Users\EmployeeEmailConfirmationRequested;
use App\Services\AccountMailEvents;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

final class TenantEmailChangeService
{
    public function __construct(private readonly TenantInvitationUrl $urls) {}

    public function send(User $member, string $email): bool
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            throw ValidationException::withMessages(['email' => 'L’envoi des e-mails est indisponible. Configurez le transport e-mail.']);
        }
        return AccountMailEvents::dispatch('tenant_email_confirmation', function () use ($member, $email): object {
            $expiresAt = now()->addMinutes(30);
            $path = URL::temporarySignedRoute('tenant.settings.email.confirm', $expiresAt, [
                'user' => $member->uuid,
                'tenant' => (string) tenant()->getTenantKey(),
                'email' => Crypt::encryptString($email),
                'previous' => hash('sha256', $member->email),
            ], absolute: false);
            $settingsPath = route('tenant.settings.show', absolute: false);
            $url = $this->urls->make('tenant.settings.show');

            return new EmployeeEmailConfirmationRequested(
                (string) tenant()->uuid, $member->uuid, $email,
                substr($url, 0, -strlen($settingsPath)).$path, $expiresAt->getTimestamp(),
            );
        }, tenant: true);
    }
}
