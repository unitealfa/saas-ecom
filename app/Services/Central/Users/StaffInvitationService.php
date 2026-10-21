<?php

namespace App\Services\Central\Users;

use App\DTOs\Central\Users\UserSaveResult;
use App\Models\Central\User;
use App\Services\Central\Auth\AccountMailDelivery;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class StaffInvitationService
{
    public function __construct(
        private AuthorizationMutation $mutations,
        private AccountMailDelivery $mail,
    ) {}

    public function resend(User $actor, User $target): UserSaveResult
    {
        $emailSent = null;
        $user = $this->mutations->run(
            actor: $actor,
            ability: 'resendStaffInvitation',
            loadTarget: fn () => \App\Models\User::whereKey($target->id)->lockForUpdate()->firstOrFail(),
            mutation: function (User $target) use ($actor, &$emailSent): User {
                $token = Password::broker('central')->createToken($target);
                DB::afterCommit(function () use ($target, $token, &$emailSent): void {
                    $emailSent = $this->mail->invitation($target, $token);
                });
                activity()->causedBy($actor)->performedOn($target)->event('account_invitation_requested')
                    ->log('Renvoi de l’invitation du personnel SaaS demandé.');

                return $target;
            },
        );

        return new UserSaveResult($user, $emailSent);
    }
}
