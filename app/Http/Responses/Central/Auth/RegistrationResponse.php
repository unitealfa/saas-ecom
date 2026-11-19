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
        $message = 'Votre compte Aydra a été créé avec succès !';

        if ($request->wantsJson()) {
            return response()->json([
                'status' => StatusEnum::PENDING->value,
                'message' => $message,
                'email_sent' => $request->session()->get('registration_email_sent', false),
                'redirect' => route('verification.notice'),
            ], 201);
        }

        $emailSent = $request->session()->get('registration_email_sent', false);
        Auth::guard('central')->logout();
        $request->session()->regenerate();

        return redirect()->route('login')->with('registration_created', true)
            ->with('registration_email_sent', $emailSent);
    }
}
