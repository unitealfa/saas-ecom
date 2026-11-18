<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Services\Tenant\Auth\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

final class PasswordResetController extends Controller
{
    public function forgot(): View { return view('tenant.pages.auth.forgot-password'); }
    public function email(Request $request, PasswordResetService $service): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $service->request($data['email']);
        return back()->with('status', 'Si cette adresse dispose d’un compte autorisé, un message sera envoyé.');
    }
    public function reset(Request $request, string $token): View
    {
        return view('tenant.pages.auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }
    public function update(Request $request, PasswordResetService $service): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if (! $service->reset($data['email'], $data['token'], $data['password'])) {
            return back()->withErrors(['email' => 'Lien invalide, expiré ou compte non autorisé.']);
        }
        return to_route('tenant.login')->with('status', 'Mot de passe modifié. Connectez-vous.');
    }
}
