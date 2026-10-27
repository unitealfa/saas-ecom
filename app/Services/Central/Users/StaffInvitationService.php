<?php

namespace App\Services\Central\Users;

use App\DTOs\Central\Users\UserSaveResult;
use App\Models\Central\Users\User;
use App\Services\Central\Auth\AccountMailDelivery;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class StaffInvitationService
{
    private const string ABILITY_RESEND = 'resendStaffInvitation';

    private const string EVENT_REQUESTED = 'account_invitation_requested';

    private const string BROKER = 'central';

    public function __construct(
        private AuthorizationMutation $mutations,
        private AccountMailDelivery $mail,
    ) {}

    public function resend(User $actor, User $target): UserSaveResult
    {
        $emailSent = null;

        $user = $this->mutations->run(
            actor: $actor,
            ability: self::ABILITY_RESEND,
            loadTarget: fn (): User => User::whereKey($target->id)->lockForUpdate()->firstOrFail(),
            mutation: function (User $target) use ($actor, &$emailSent): User {
                $token = $this->broker()->createToken($target);

                DB::afterCommit(function () use ($target, $token, &$emailSent): void {
                    $emailSent = $this->mail->invitation($target, $token);
                });

                activity()->causedBy($actor)->performedOn($target)->event(self::EVENT_REQUESTED)
                    ->log('Renvoi de l’invitation du personnel SaaS demandé.');

                return $target;
            },
        );

        return new UserSaveResult($user, $emailSent);
    }

    private function broker(): PasswordBroker
    {
        $broker = Password::broker(self::BROKER);

        assert($broker instanceof PasswordBroker);

        return $broker;
    }
}
