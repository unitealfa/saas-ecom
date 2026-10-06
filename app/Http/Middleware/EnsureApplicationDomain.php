<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getHost(), config('tenancy.central_domains'), true)) {
            abort_unless(in_array(InitializeTenancyByDomain::class, $request->route()?->gatherMiddleware() ?? [], true), 404);
        }

        return $next($request);
    }
}
