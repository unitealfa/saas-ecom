<?php

namespace App\Http\Controllers\Tenant\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;
use App\Services\Tenant\Users\TenantEmailChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EmailSettingsController extends Controller
{
    public function requestChange(Request $request, TenantEmailChangeService $emails): RedirectResponse
    {
        $actor = $this->employee();
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('tenant.users', 'email')],
            'current_password' => ['required', 'string'],
        ]);
        if (! Hash::check($data['current_password'], $actor->password)) {
            throw ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }
        $emails->send($actor, $data['email']);

        return redirect()->route('tenant.settings.show')->with('status', 'Un lien de confirmation a été envoyé à la nouvelle adresse.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $actor = $this->employee();
        abort_unless($request->query('user') === $actor->uuid
            && $request->query('tenant') === (string) tenant()->getTenantKey(), 403);
        $email = Crypt::decryptString((string) $request->query('email'));
        DB::connection('tenant')->transaction(function () use ($actor, $request, $email): void {
            $user = User::whereKey($actor->getKey())->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            abort_unless(TenantAccess::active($user) && TenantAccess::editableEmployee($user), 403);
            abort_unless(hash_equals(hash('sha256', $user->email), (string) $request->query('previous')), 403);
            if (User::withTrashed()->where('email', $email)->whereKeyNot($user->getKey())->exists()) {
                throw ValidationException::withMessages(['email' => 'Cette adresse est déjà utilisée dans la boutique.']);
            }
            $user->forceFill(['email' => $email, 'email_verified_at' => now()])->save();
            activity()->causedBy($user)->performedOn($user)->event('tenant_email_changed')->log('Adresse e-mail locale modifiée.');
        });

        return redirect()->route('tenant.settings.show')->with('status', 'Adresse e-mail confirmée.');
    }

    private function employee(): User
    {
        $user = $this->tenantActor();
        abort_unless(TenantAccess::active($user) && TenantAccess::editableEmployee($user), 403);

        return $user;
    }
}
