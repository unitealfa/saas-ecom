<?php

namespace App\Services\Tenant\Auth;

use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginService
{
    public function login(string $email, string $password, bool $remember): User
    {
        abort_unless(tenancy()->initialized, 403);
        $user = User::where('email', mb_strtolower(trim($email)))->first();
        if (! $user instanceof User || ! TenantAccess::editableEmployee($user)
            || ! TenantAccess::active($user) || ! Auth::guard('tenant')->attempt([
                'email' => $user->email, 'password' => $password,
            ], $remember)) {
            throw ValidationException::withMessages(['email' => 'Identifiants invalides.']);
        }
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }
}
