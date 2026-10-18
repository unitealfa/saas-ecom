<?php

namespace App\Http\Responses\Central\Auth;

use App\Services\Central\Authorization\CentralUserAccess;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Http\Responses\VerifyEmailResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifiedEmailResponse extends VerifyEmailResponse
{
    public function __construct(private CentralUserAccess $access) {}

    public function toResponse($request): Response
    {
        if ($this->access->isStaff($request->user('central'))) {
            return parent::toResponse($request);
        }

        Auth::guard('central')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->wantsJson()
            ? response()->noContent()
            : redirect()->route('login')->with('status', 'Votre adresse e-mail est vérifiée. Connectez-vous pour consulter la prochaine étape de votre inscription.');
    }
}
