<?php

namespace App\Http\Middleware\Central\Auth;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCentralAccount
{
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        abort_if(tenancy()->initialized, 403);
        $this->checkAccount($request, $mode === 'optional');
        $response = $next($request);
        if ($mode === 'optional') {
            $this->checkAccount($request, true);
        }

        return $response;
    }

    private function checkAccount(Request $request, bool $allowGuest): void
    {
        $user = $request->user('central');
        if ($user === null && $allowGuest) {
            return;
        }
        if (! $user instanceof User || $user->fresh()?->status !== StatusEnum::ACTIVE) {
            Auth::guard('central')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403);
        }
    }
}
