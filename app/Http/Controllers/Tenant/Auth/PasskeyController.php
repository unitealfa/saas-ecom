<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Auth\TenantPasskeys;
use App\Services\Tenant\Authorization\TenantAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Support\WebAuthn;

final class PasskeyController extends Controller
{
    public function __construct(private TenantPasskeys $passkeys) {}

    public function prepare(Request $request): JsonResponse
    {
        $user = $this->employee();
        $data = $request->validate(['current_password' => ['required', 'string']]);
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe incorrect.']);
        }
        $request->session()->put('tenant_passkey_authorization', ['uuid' => $user->uuid, 'until' => now()->addMinutes(5)->timestamp]);

        return response()->json(['ok' => true]);
    }

    public function registrationOptions(Request $request): JsonResponse
    {
        $user = $this->employee();
        $this->authorized($request, $user);
        $options = $this->passkeys->registrationOptions($user, $request->getHost());
        $request->session()->put('passkey.registration_options', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function register(PasskeyRegistrationRequest $request): JsonResponse
    {
        $user = $this->employee();
        $this->authorized($request, $user);
        $request->session()->forget('tenant_passkey_authorization');
        $passkey = $this->passkeys->register($user, $request->string('name')->toString(), $request->credential(),
            $request->registrationOptions(), $request->getHost(), $request->getSchemeAndHttpHost());

        return response()->json(['id' => $passkey->uuid, 'name' => $passkey->name]);
    }

    public function loginOptions(Request $request): JsonResponse
    {
        $options = $this->passkeys->loginOptions($request->getHost());
        $request->session()->put('passkey.verification_options', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function login(PasskeyVerificationRequest $request): JsonResponse
    {
        $passkey = $this->passkeys->verify($request->credential(), $request->verificationOptions(),
            $request->getHost(), $request->getSchemeAndHttpHost());
        $user = $passkey->user;
        abort_unless($user instanceof User && TenantAccess::active($user) && TenantAccess::editableEmployee($user), 403);
        Auth::guard('tenant')->login($user, $request->remember());
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['redirect' => route('tenant.dashboard', absolute: false)]);
    }

    public function destroy(Request $request, string $uuid): RedirectResponse
    {
        $user = $this->employee();
        $data = $request->validate(['current_password' => ['required', 'string']]);
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe incorrect.']);
        }
        $user->passkeys()->where('uuid', $uuid)->firstOrFail()->delete();

        return redirect()->route('tenant.settings.show')->with('status', 'Clé d’accès supprimée.');
    }

    private function employee(): User
    {
        $user = $this->tenantActor();
        abort_unless(TenantAccess::active($user) && TenantAccess::editableEmployee($user), 403);

        return $user;
    }

    private function authorized(Request $request, User $user): void
    {
        $authorization = $request->session()->get('tenant_passkey_authorization');
        abort_unless(is_array($authorization) && ($authorization['uuid'] ?? null) === $user->uuid
            && ($authorization['until'] ?? 0) >= now()->timestamp, 403);
    }
}
