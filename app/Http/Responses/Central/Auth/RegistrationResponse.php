<?php

namespace App\Http\Responses\Central\Auth;

use App\Enums\Central\Users\StatusEnum;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

class RegistrationResponse implements RegisterResponse
{
    public function toResponse($request): Response
    {
        Auth::guard('central')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $message = 'Votre compte est créé et en attente d’approbation administrative. Vous pourrez vous connecter après validation.';

        return $request->wantsJson()
            ? response()->json(['status' => StatusEnum::PENDING->value, 'message' => $message], 201)
            : redirect()->route('login')->with('status', $message);
    }
}
