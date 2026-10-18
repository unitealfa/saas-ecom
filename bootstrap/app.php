<?php

use App\Http\Middleware\EnsureApplicationDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [EnsureApplicationDomain::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($request->routeIs('verification.verify') && ! $request->expectsJson()
                && ($exception instanceof InvalidSignatureException || $exception->getPrevious() instanceof AuthorizationException)) {
                return response()->view('central.pages.auth.verification-error', [], 403);
            }
            if ($exception->getStatusCode() === 429 && $request->routeIs('verification.send')
                && ! $request->expectsJson() && $request->user('central') !== null) {
                return response()->view('central.pages.auth.verify-email', [
                    'verificationError' => 'Trop de demandes rapprochées. Patientez une minute avant de renvoyer un e-mail.',
                ], 429, $exception->getHeaders());
            }
        });
        $exceptions->render(function (TenantCouldNotBeIdentifiedOnDomainException $exception, Request $request): Response {
            return response('Not Found', 404);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
