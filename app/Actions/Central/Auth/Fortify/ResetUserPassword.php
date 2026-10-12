<?php

namespace App\Actions\Central\Auth\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\Central\Activity;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->getConnection()->transaction(function () use ($user, $input): void {
            $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($current->email !== ($input['email'] ?? null)
                || ! Password::broker('central')->tokenExists($current, $input['token'] ?? '')) {
                throw ValidationException::withMessages(['email' => 'Ce lien est invalide ou a expiré.']);
            }
            $invited = ! $current->hasVerifiedEmail() && Activity::forSubject($current)
                ->whereIn('event', ['account_invited', 'account_invitation_requested'])->exists();
            $current->forceFill(['password' => $input['password']]);
            if ($invited) {
                $current->email_verified_at = now();
            }
            $current->save();
            Password::broker('central')->deleteToken($current);
            $user->setRawAttributes($current->getAttributes(), true);
            if ($invited) {
                activity()->performedOn($current)->event('account_invitation_accepted')
                    ->log('Invitation acceptée et adresse e-mail confirmée par le lien sécurisé.');
                $current->getConnection()->afterCommit(fn () => event(new Verified($current)));
            }
        }, 3);
    }
}
