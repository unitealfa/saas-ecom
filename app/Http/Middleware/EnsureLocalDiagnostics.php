<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalDiagnostics
{
    private const array LOOPBACK_IPS = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->isAllowed($request), 404);

        return $next($request);
    }

    public function isAllowed(Request $request): bool
    {
        return app()->environment(['local', 'testing'])
            && config('app.debug')
            && in_array($request->server('REMOTE_ADDR'), self::LOOPBACK_IPS, true);
    }
}
