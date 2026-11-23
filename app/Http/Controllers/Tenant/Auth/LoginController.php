<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Services\Tenant\Auth\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LoginController extends Controller
{
    public function __invoke(Request $request, LoginService $login): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        $user = $login->login($data['email'], $data['password'], $request->boolean('remember'));

        $request->session()->regenerate();
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put('tenant_two_factor', [
                'uuid' => $user->uuid,
                'tenant' => (string) tenant()->getTenantKey(),
                'expires' => now()->addMinutes(5)->timestamp,
                'remember' => $request->boolean('remember'),
            ]);

            return to_route('tenant.two-factor.challenge');
        }

        return to_route('tenant.dashboard');
    }
}
