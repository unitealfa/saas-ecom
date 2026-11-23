<?php

namespace App\Http\Controllers\Tenant\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorSettingsController extends Controller
{
    public function enable(Request $request): RedirectResponse
    {
        $enable = new EnableTwoFactorAuthentication($this->provider());
        $this->change($request, fn (User $user) => $enable($user), 'tenant_two_factor_setup');
        $request->session()->put('tenant_two_factor_setup_until', now()->addMinutes(10)->timestamp);

        return redirect()->route('tenant.settings.show')->with('status', 'Scannez le code puis confirmez avec votre application.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $confirm = new ConfirmTwoFactorAuthentication($this->provider());
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);
        $user = $this->change($request, fn (User $user) => $confirm($user, $data['code']), 'tenant_two_factor_enabled');
        $request->session()->forget('tenant_two_factor_setup_until');
        $request->session()->flash('tenant_recovery_codes', $user->recoveryCodes());

        return redirect()->route('tenant.settings.show')->with('status', 'Double authentification activée.');
    }

    public function disable(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $this->change($request, fn (User $user) => $disable($user), 'tenant_two_factor_disabled');
        $request->session()->forget('tenant_two_factor_setup_until');

        return redirect()->route('tenant.settings.show')->with('status', 'Double authentification désactivée.');
    }

    public function recovery(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $user = $this->change($request, function (User $user) use ($generate): void {
            abort_unless($user->hasEnabledTwoFactorAuthentication(), 403);
            $generate($user);
        }, 'tenant_two_factor_recovery_regenerated');
        $request->session()->flash('tenant_recovery_codes', $user->recoveryCodes());

        return redirect()->route('tenant.settings.show')->with('status', 'Nouveaux codes de récupération créés.');
    }

    private function change(Request $request, callable $action, string $event): User
    {
        $actor = $this->tenantActor();
        $data = $request->validate(['current_password' => ['required', 'string']]);

        return DB::connection('tenant')->transaction(function () use ($actor, $data, $action, $request, $event): User {
            $user = User::whereKey($actor->getKey())->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            abort_unless(TenantAccess::active($user) && TenantAccess::editableEmployee($user), 403);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
            }
            $action($user);
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            DB::connection('tenant')->table('sessions')->where('user_id', $user->getKey())
                ->where('id', '!=', $request->session()->getId())->delete();
            activity()->causedBy($user)->performedOn($user)->event($event)->log('Sécurité du compte boutique modifiée.');

            return $user;
        });
    }

    private function provider(): TwoFactorAuthenticationProvider
    {
        return new TwoFactorAuthenticationProvider(new Google2FA, Cache::store());
    }
}
