<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View
    {
        $this->challenge($request);

        return view('tenant.pages.auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $provider = new TwoFactorAuthenticationProvider(new Google2FA, Cache::store());
        $challenge = $this->challenge($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);
        $user = DB::connection('tenant')->transaction(function () use ($challenge, $data, $provider): User {
            $user = User::where('uuid', $challenge['uuid'])->lockForUpdate()->firstOrFail();
            abort_unless(TenantAccess::active($user) && TenantAccess::editableEmployee($user) && $user->hasEnabledTwoFactorAuthentication(), 403);
            $valid = false;
            foreach ($user->recoveryCodes() as $code) {
                if (hash_equals($code, $data['code'])) {
                    $user->replaceRecoveryCode($code);
                    $valid = true;
                    break;
                }
            }
            if (! $valid && ctype_digit($data['code'])) {
                $valid = $provider->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $data['code']);
            }
            if (! $valid) {
                throw ValidationException::withMessages(['code' => 'Code incorrect.']);
            }
            $user->forceFill(['last_login_at' => now()])->save();

            return $user;
        });
        $request->session()->forget('tenant_two_factor');
        Auth::guard('tenant')->login($user, (bool) $challenge['remember']);
        $request->session()->regenerate();

        return to_route('tenant.dashboard');
    }

    /** @return array{uuid: string, tenant: string, expires: int, remember: bool} */
    private function challenge(Request $request): array
    {
        $challenge = $request->session()->get('tenant_two_factor');
        abort_unless(is_array($challenge) && isset($challenge['uuid'], $challenge['tenant'], $challenge['expires'], $challenge['remember'])
            && $challenge['tenant'] === (string) tenant()->getTenantKey() && $challenge['expires'] >= now()->timestamp, 403);

        return $challenge;
    }
}
