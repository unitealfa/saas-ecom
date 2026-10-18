<?php

namespace App\Http\Responses\Central\Auth;

use App\Services\Central\Auth\RegistrationJourney;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract, TwoFactorLoginResponse
{
    public function __construct(private RegistrationJourney $journey) {}

    public function toResponse($request): Response
    {
        $destination = $this->journey->destination($request->user('central'));
        $intended = $request->session()->pull('url.intended');
        $verificationLink = is_string($intended) && str_starts_with($intended, route('verification.notice').'/');
        $internalLink = is_string($intended)
            && str_starts_with($intended, rtrim(config('app.url'), '/').'/');
        // Les comptes restreints reprennent uniquement le lien de vérification Fortify.
        if ($verificationLink || ($destination === route('dashboard') && $internalLink)) {
            $destination = $intended;
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false, 'redirect' => $destination])
            : redirect()->to($destination);
    }
}
