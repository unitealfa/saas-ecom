<?php

use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Http\Middleware\Tenant\EnsureTenantAvailable;
use App\Http\Middleware\Tenant\FinishTenantRequest;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

test('every diagnostic endpoint applies the local diagnostic boundary', function (): void {
    $diagnostics = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains($route->uri(), '_dev/'));

    expect($diagnostics)->not->toBeEmpty();
    foreach ($diagnostics as $route) {
        expect($route->gatherMiddleware())->toContain(EnsureLocalDiagnostics::class);
    }
});

test('central account routes require authentication while explicit public endpoints retain their boundaries', function (): void {
    $unprotected = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->getName() ?? '', 'central.'))
        ->reject(fn ($route): bool => Str::is([
            'central.database-diagnostics', 'central.alias.*.home',
            'central.alias.*.well-known.passkeys', 'central.alias.*.central.database-diagnostics',
        ], $route->getName()))
        ->reject(fn ($route): bool => in_array('auth:central', $route->gatherMiddleware(), true))
        ->map(fn ($route): string => $route->getName())->values()->all();

    expect($unprotected)->toBe([]);
});

test('passkey discovery remains public and only exposes protected settings URLs', function (): void {
    $this->get(route('well-known.passkeys'))->assertOk()->assertExactJson([
        'enroll' => route('security.edit'), 'manage' => route('security.edit'),
    ]);
    $this->get(route('security.edit'))->assertRedirect(route('login'));
});

test('application HTTP routes have controller actions rather than closures', function (): void {
    $closures = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => $route->getAction('uses') instanceof Closure)
        ->filter(fn ($route): bool => str_starts_with(
            str_replace('\\', '/', (new ReflectionFunction($route->getAction('uses')))->getFileName()),
            str_replace('\\', '/', base_path('routes/')),
        ))
        ->map(fn ($route): string => $route->uri())->values()->all();

    expect($closures)->toBe([]);
});

test('the HTTP kernel wraps tenant initialization with availability and cleanup middleware', function (): void {
    $expected = [FinishTenantRequest::class, PreventAccessFromCentralDomains::class,
        EnsureTenantAvailable::class, InitializeTenancyByDomain::class];
    $priority = app(Kernel::class)->getMiddlewarePriority();

    expect(array_values(array_intersect($priority, $expected)))->toBe($expected);
});

test('central alias home pages keep redirecting to the canonical Fortify login', function (string $host): void {
    $this->get('http://'.$host.'/')->assertRedirect(route('login'));
})->with(['localhost', '127.0.0.1']);
