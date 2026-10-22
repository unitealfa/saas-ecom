<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isCentralDomain($request)) {
            abort_unless($this->initializesTenancy($request->route()), 404);
        }

        return $next($request);
    }

    private function isCentralDomain(Request $request): bool
    {
        $centralDomains = array_map(
            'strtolower',
            array_map('trim', (array) config('tenancy.central_domains', []))
        );

        return in_array(strtolower($request->getHost()), $centralDomains, true);
    }

    private function initializesTenancy(?Route $route): bool
    {
        if ($route === null) {
            return false;
        }

        $middleware = app('router')->resolveMiddleware(
            $route->gatherMiddleware(),
            $route->excludedMiddleware()
        );

        foreach ($middleware as $item) {
            if (is_string($item) && strtok($item, ':') === InitializeTenancyByDomain::class) {
                return true;
            }
        }

        return false;
    }
}