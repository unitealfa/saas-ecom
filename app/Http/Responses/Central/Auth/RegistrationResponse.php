<?php

namespace App\Http\Responses\Central\Auth;

use App\Enums\Central\Users\StatusEnum;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

class RegistrationResponse implements RegisterResponse
{
    public function toResponse($request): Response
    {
        $message = 'Votre compte Aydra a été créé avec succès !';

        return $request->wantsJson()
            ? response()->json([
                'status' => StatusEnum::PENDING->value,
                'message' => $message,
                'email_sent' => $request->session()->get('registration_email_sent', false),
                'redirect' => route('verification.notice'),
            ], 201)
            : redirect()->route('verification.notice')->with('registration_created', true);
    }
}
