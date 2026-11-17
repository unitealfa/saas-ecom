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
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string']]);
        $login->login($data['email'], $data['password']);
        $request->session()->regenerate(true);
        return to_route('tenant.dashboard');
    }
}
