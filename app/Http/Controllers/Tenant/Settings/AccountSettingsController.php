<?php

namespace App\Http\Controllers\Tenant\Settings;

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Central\Countries\Country;
use App\Models\Tenant\Users\User;
use App\Services\Central\Countries\CountryCatalog;
use App\Services\Central\Users\PhoneNumberNormalizer;
use App\Services\Tenant\Authorization\TenantAccess;
use App\Services\Tenant\Dashboard\Dashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class AccountSettingsController extends Controller
{
    public function show(Dashboard $dashboard, CountryCatalog $countries): View|RedirectResponse
    {
        $actor = $this->employee();
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('passkeys') || ! $schema->hasColumns('users', [
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'country_uuid', 'phone_country_uuid',
        ])) {
            return redirect()->route('tenant.dashboard')->with('status', 'Les paramètres du compte seront disponibles après la mise à jour de cette boutique.');
        }

        return view('tenant.pages.settings.profile', [
            'user' => $actor,
            'dashboard' => $dashboard->forUser($actor),
            'countries' => $countries->active()->merge(Country::withTrashed()
                ->whereIn('uuid', array_filter([$actor->country_uuid, $actor->phone_country_uuid]))->get())->unique('uuid'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $actor = $this->employee();
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:150'],
            'last_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'country_uuid' => ['nullable', 'uuid'],
            'phone_country_uuid' => ['nullable', 'uuid'],
        ]);
        foreach (['country_uuid', 'phone_country_uuid'] as $field) {
            if (! empty($data[$field]) && $data[$field] !== $actor->{$field}
                && ! Country::active()->where('uuid', $data[$field])->exists()) {
                throw ValidationException::withMessages([$field => 'Choisissez un pays actif.']);
            }
        }
        if (! empty($data['phone'])) {
            $country = Country::withTrashed()->where('uuid', $data['phone_country_uuid'] ?? null)->first();
            if (! $country) {
                throw ValidationException::withMessages(['phone_country_uuid' => 'Choisissez le pays du téléphone.']);
            }
            $data['phone'] = app(PhoneNumberNormalizer::class)->normalize($data['phone'], $country->code);
            if ((! $country->is_active || $country->trashed()) && $data['phone'] !== $actor->phone) {
                throw ValidationException::withMessages(['phone_country_uuid' => 'Choisissez un pays actif pour un nouveau numéro.']);
            }
        }

        DB::connection('tenant')->transaction(function () use ($actor, $data): void {
            $user = User::whereKey($actor->getKey())->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            abort_unless(TenantAccess::active($user) && TenantAccess::editableEmployee($user), 403);
            $user->fill(Arr::only($data, ['first_name', 'last_name', 'phone']))
                ->forceFill(Arr::only($data, ['country_uuid', 'phone_country_uuid']))->save();
        });

        return redirect()->route('tenant.settings.show')->with('status', 'Profil enregistré.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $actor = $this->employee();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        DB::connection('tenant')->transaction(function () use ($actor, $data, $request): void {
            $user = User::whereKey($actor->getKey())->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            abort_unless(TenantAccess::active($user) && TenantAccess::editableEmployee($user), 403);

            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
            }

            $user->forceFill(['password' => $data['password'], 'remember_token' => null])->save();
            DB::connection('tenant')->table('sessions')->where('user_id', $user->getKey())
                ->where('id', '!=', $request->session()->getId())->delete();
            activity()->causedBy($user)->performedOn($user)->event('tenant_password_changed')
                ->log('Mot de passe local modifié.');
        });

        return redirect()->route('tenant.settings.show')->with('status', 'Mot de passe modifié.');
    }

    private function employee(): User
    {
        $actor = $this->tenantActor();
        abort_unless(TenantAccess::active($actor) && TenantAccess::editableEmployee($actor), 403);

        return $actor;
    }

    public function destroy(Request $request): RedirectResponse
    {
        $actor = $this->employee();
        $data = $request->validate(['current_password' => ['required', 'string'], 'confirm' => ['accepted']]);
        DB::connection('tenant')->transaction(function () use ($actor, $data): void {
            $user = User::whereKey($actor->getKey())->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            abort_unless(TenantAccess::active($user) && TenantAccess::editableEmployee($user), 403);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
            }
            $user->forceFill(['status' => StatusEnum::DELETED, 'membership_status' => MembershipStatusEnum::REVOKED, 'remember_token' => null])->save();
            $user->delete();
            DB::connection('tenant')->table('sessions')->where('user_id', $user->getKey())->delete();
            activity()->causedBy($user)->performedOn($user)->event('tenant_account_deleted')->log('Compte boutique fermé.');
        });
        Auth::guard('tenant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('tenant.login')->with('status', 'Votre compte de cette boutique est fermé.');
    }
}
