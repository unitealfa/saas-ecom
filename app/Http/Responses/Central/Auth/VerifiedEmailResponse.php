<?php

namespace App\Http\Responses\Central\Auth;

use App\Models\Central\Users\User;
use App\Services\Central\Auth\RegistrationJourney;
use App\Services\Central\Authorization\CentralUserAccess;
use Laravel\Fortify\Http\Responses\VerifyEmailResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifiedEmailResponse extends VerifyEmailResponse
{
    public function __construct(private CentralUserAccess $access, private RegistrationJourney $journey) {}

    public function toResponse($request): Response
    {
        $user = $request->user('central');
        abort_unless($user instanceof User, 401);
        if ($this->access->isStaff($user)) {
            return parent::toResponse($request);
        }

        $user = $user->fresh();
        abort_unless($user instanceof User, 401);

        return $request->wantsJson()
            ? response()->noContent()
            : redirect()->to($this->journey->destination($user))
                ->with('status', 'Votre adresse e-mail est vérifiée.');
    }
}
