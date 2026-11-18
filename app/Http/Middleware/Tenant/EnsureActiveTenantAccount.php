<?php

namespace App\Http\Middleware\Tenant;

use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveTenantAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(tenancy()->initialized, 403);
        $actor = $request->user('tenant');
        abort_unless($actor instanceof User, 401);
        $current = User::whereKey($actor->id)->where('uuid', $actor->uuid)->first();
        if (! $current || ! TenantAccess::active($current)) {
            abort(403, 'Compte ou appartenance boutique inactifs.');
        }
        // Rebinding prevents a stale in-memory user from retaining revoked privileges.
        auth('tenant')->setUser($current);
        return $next($request);
    }
}
