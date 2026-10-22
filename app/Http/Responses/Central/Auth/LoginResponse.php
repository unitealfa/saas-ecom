<?php

namespace App\Http\Responses\Central\Auth;

use App\Enums\Central\Users\JourneyState;
use App\Models\Central\User;
use App\Services\Central\Auth\RegistrationJourney;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract, TwoFactorLoginResponse
{
    private const string GUARD = 'central';

    public function __construct(private RegistrationJourney $journey) {}

    public function toResponse($request): Response
    {
        $user = $request->user(self::GUARD);
        abort_unless($user instanceof User, 401);
        $intended = $request->session()->pull('url.intended');

        $state = $this->journey->state($user);
        $destination = route($state->routeName());

        if ($this->shouldFollowIntended($state, $intended)) {
            $destination = $intended;
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false, 'redirect' => $destination])
            : redirect()->to($destination);
    }

    /** Les comptes restreints reprennent uniquement le lien de vérification Fortify. */
    private function shouldFollowIntended(JourneyState $state, mixed $intended): bool
    {
        if (! is_string($intended)) {
            return false;
        }

        if (str_starts_with($intended, route('verification.notice').'/')) {
            return true;
        }

        return $state === JourneyState::READY
            && str_starts_with($intended, rtrim(config('app.url'), '/').'/');
    }
}
