<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\OwnerLoginRequest;
use App\Services\Tenant\Auth\OwnerLoginService;
use App\Services\Tenant\Shop\ShopProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OwnerLoginController extends Controller
{
    public function show(Request $request, ShopProfileService $profiles): View|RedirectResponse
    {
        if (Auth::guard('tenant')->check()) {
            return to_route('tenant.dashboard');
        }
        $nonce = bin2hex(random_bytes(32));
        $request->session()->put(OwnerLoginRequest::NONCE, $nonce);

        return view('tenant.pages.auth.login', ['shopName' => $profiles->shop()->shop_name,
            'ownerAccessUrl' => route('owner.shops.access', ['tenant' => tenant()->uuid, 'nonce' => $nonce])]);
    }

    public function accept(OwnerLoginRequest $request, OwnerLoginService $login): RedirectResponse
    {
        $data = $request->validated();
        $user = $login->accept($data['owner'], $data['tenant_uuid'], $data['email_hash']);
        $request->session()->forget(OwnerLoginRequest::NONCE);
        Auth::guard('tenant')->login($user);
        $request->session()->regenerate(true);

        return to_route('tenant.dashboard')->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store']);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->tenantActor();
        Auth::guard('tenant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('tenant.login');
    }
}
