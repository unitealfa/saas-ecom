<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalDiagnostics
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            app()->environment(['local', 'testing'])
                && config('app.debug')
                && in_array($request->ip(), ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true),
            404,
        );

        return $next($request);
    }
}
