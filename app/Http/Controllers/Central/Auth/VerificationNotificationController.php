<?php

namespace App\Http\Controllers\Central\Auth;

use App\Models\Central\User;
use App\Services\Central\Auth\AccountMailDelivery;
use App\Services\Central\Auth\RegistrationJourney;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;

class VerificationNotificationController extends EmailVerificationNotificationController
{
    public function __construct(private AccountMailDelivery $mail, private RegistrationJourney $journey) {}

    public function store(Request $request)
    {
        $user = $request->user('central');
        abort_unless($user instanceof User, 403);

        if ($user->hasVerifiedEmail()) {
            return $request->wantsJson()
                ? response()->noContent()
                : redirect()->to($this->journey->destination($user));
        }

        if (! $this->mail->verification($user)) {
            return $request->wantsJson()
                ? response()->json(['message' => 'L’e-mail n’a pas pu être envoyé. Réessayez dans quelques instants.'], 503)
                : redirect()->route('verification.notice')->withErrors(['email' => 'L’e-mail n’a pas pu être envoyé. Votre compte est conservé ; réessayez dans quelques instants.']);
        }

        return app(EmailVerificationNotificationSentResponse::class);
    }
}
