<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\OwnerLoginRequest;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Auth\OwnerLoginService;
use App\Services\Tenant\Authorization\TenantAccess;
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

        return view('tenant.pages.auth.login', ['shopName' => $profiles->shop()->shop_name,
            'ownerAccessUrl' => $this->ownerAccessUrl($request)]);
    }

    public function start(Request $request): RedirectResponse
    {
        abort_unless(tenancy()->initialized && $request->query('tenant_uuid') === tenant()->uuid, 403);

        $user = Auth::guard('tenant')->user();
        if ($user instanceof User && TenantAccess::active($user) && TenantAccess::isOwner($user)) {
            return to_route('tenant.dashboard');
        }

        return redirect()->to($this->ownerAccessUrl($request))
            ->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store']);
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

    private function ownerAccessUrl(Request $request): string
    {
        $nonce = bin2hex(random_bytes(32));
        $request->session()->put(OwnerLoginRequest::NONCE, $nonce);

        return route('owner.shops.access', ['tenant' => tenant()->uuid, 'nonce' => $nonce]);
    }
}
