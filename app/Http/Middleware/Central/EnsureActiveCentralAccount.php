<?php

namespace App\Http\Middleware\Central;

use App\Enums\Users\StatusEnum;
use App\Models\Central\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCentralAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('central');
        abort_unless(! tenancy()->initialized && $user instanceof User
            && $user->fresh()?->status === StatusEnum::ACTIVE, 403);

        return $next($request);
    }
}
