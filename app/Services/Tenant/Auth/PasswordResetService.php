<?php

namespace App\Services\Tenant\Auth;

use App\Models\Tenant\Users\User;
use App\Notifications\Tenant\ResetEmployeePasswordNotification;
use App\Services\Tenant\Users\TenantInvitationUrl;
use App\Services\Tenant\Authorization\TenantAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class PasswordResetService
{
    public function __construct(private readonly TenantInvitationUrl $urls) {}

    public function request(string $email): void
    {
        abort_unless(tenancy()->initialized, 403);
        abort_unless(config('mail.default') !== 'log', 503, 'Transport e-mail journalisé non autorisé pour la récupération.');
        $user = User::where('email', mb_strtolower(trim($email)))->first();
        if (! $user || ! TenantAccess::editableEmployee($user) || ! TenantAccess::active($user)) {
            return; // Identical public response for unknown and blocked accounts.
        }
        Password::broker('tenant')->sendResetLink(['email' => $user->email], function (User $recipient, string $token): void {
            $recipient->notify(new ResetEmployeePasswordNotification($this->urls->make('tenant.password.reset', [
                'token' => $token, 'email' => $recipient->email,
            ])));
        });
    }

    public function reset(string $email, string $token, string $password): bool
    {
        abort_unless(tenancy()->initialized, 403);
        $email = mb_strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (! $user || ! TenantAccess::editableEmployee($user) || ! TenantAccess::active($user)) {
            return false;
        }
        $result = Password::broker('tenant')->reset([
            'email' => $email, 'token' => $token, 'password' => $password,
            'password_confirmation' => $password,
        ], function (User $target, string $newPassword): void {
            DB::connection('tenant')->transaction(function () use ($target, $newPassword): void {
                $locked = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
                abort_unless(TenantAccess::editableEmployee($locked) && TenantAccess::active($locked), 403);
                $locked->forceFill(['password' => $newPassword, 'remember_token' => Str::random(60)])->save();
                DB::connection('tenant')->table('sessions')->where('user_id', $locked->id)->delete();
                activity()->causedBy($locked)->performedOn($locked)->event('tenant_password_reset')
                    ->log('Mot de passe local réinitialisé');
            }, 3);
        });
        return $result === Password::PASSWORD_RESET;
    }
}
