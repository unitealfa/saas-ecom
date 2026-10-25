<?php

use App\Enums\Central\Users\StatusEnum;
use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use App\Http\Middleware\EnsureApplicationDomain;
use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function accountBoundaryResponse(string $routeName, string $mode): TestResponse
{
    $request = Request::create(route($routeName));
    $request->setRouteResolver(fn () => app('router')->getRoutes()->getByName($routeName));
    $request->setUserResolver(fn () => Auth::guard('central')->user());
    $request->setLaravelSession(app('session')->driver());

    try {
        $response = app(EnsureActiveCentralAccount::class)->handle($request, fn () => response('passed'), $mode);
    } catch (HttpResponseException $exception) {
        $response = $exception->getResponse();
    } catch (HttpExceptionInterface $exception) {
        $response = response('', $exception->getStatusCode());
    }

    return TestResponse::fromBaseResponse($response);
}

test('account middleware respects its mode and account status', function (StatusEnum $status, string $mode, string $routeName, int $expected): void {
    $user = User::factory()->create(['status' => $status]);
    $this->actingAs($user, 'central');

    $response = accountBoundaryResponse($routeName, $mode);

    $response->assertStatus($expected);
    if ($expected === Response::HTTP_FOUND) {
        $response->assertRedirect(route('registration.status'));
    }
    if ($expected === Response::HTTP_FORBIDDEN) {
        $this->assertGuest('central');
    }
})->with([
    'active required' => [StatusEnum::ACTIVE, 'required', 'central.users.index', 200],
    'active optional' => [StatusEnum::ACTIVE, 'optional', 'registration.status', 200],
    'pending required' => [StatusEnum::PENDING, 'required', 'central.users.index', 302],
    'pending optional' => [StatusEnum::PENDING, 'optional', 'registration.status', 200],
    'rejected required' => [StatusEnum::REJECTED, 'required', 'central.users.index', 302],
    'rejected optional' => [StatusEnum::REJECTED, 'optional', 'registration.status', 200],
    'suspended required' => [StatusEnum::SUSPENDED, 'required', 'central.users.index', 403],
    'suspended optional' => [StatusEnum::SUSPENDED, 'optional', 'registration.status', 403],
]);

test('guest account access is allowed only in optional mode', function (): void {
    accountBoundaryResponse('login', 'optional')->assertOk();
    accountBoundaryResponse('central.users.index', 'required')->assertForbidden();
});

test('optional mode rejects an account suspended during authentication', function (): void {
    $user = User::factory()->create(['status' => StatusEnum::SUSPENDED]);
    $request = Request::create(route('login.store'), 'POST');
    $request->setRouteResolver(fn () => app('router')->getRoutes()->getByName('login.store'));
    $request->setUserResolver(fn () => Auth::guard('central')->user());
    $request->setLaravelSession(app('session')->driver());

    try {
        app(EnsureActiveCentralAccount::class)->handle($request, function () use ($user): Response {
            Auth::guard('central')->login($user);

            return response('signed in');
        }, 'optional');
        $this->fail('A suspended account remained authenticated.');
    } catch (HttpExceptionInterface $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }

    $this->assertGuest('central');
});

test('limited accounts reach their status page without redirecting in a loop', function (StatusEnum $status): void {
    $user = User::factory()->create(['status' => $status]);

    $this->actingAs($user, 'central')->get(route('dashboard'))
        ->assertRedirect(route('registration.status'));
    $this->get(route('registration.status'))->assertOk()->assertViewIs('central.pages.auth.registration-status');
})->with([StatusEnum::PENDING, StatusEnum::REJECTED]);

test('application domains allow central hosts and only initialized tenant routes elsewhere', function (string $host, bool $tenantRoute, bool $excluded, bool $allowed): void {
    $request = Request::create('http://'.$host.'/login');
    $route = new Route('GET', 'login', ['middleware' => $tenantRoute ? [InitializeTenancyByDomain::class] : []]);
    if ($excluded) {
        $route->withoutMiddleware(InitializeTenancyByDomain::class);
    }
    $request->setRouteResolver(fn () => $route);

    if (! $allowed) {
        expect(fn () => app(EnsureApplicationDomain::class)->handle($request, fn () => response('passed')))
            ->toThrow(NotFoundHttpException::class);

        return;
    }

    expect(app(EnsureApplicationDomain::class)->handle($request, fn () => response('passed'))->getStatusCode())->toBe(200);
})->with([
    'central host' => ['aydra.localhost', false, false, true],
    'unknown host with central route' => ['unknown.example', false, false, false],
    'tenant route' => ['boutique.aydra.localhost', true, false, true],
    'excluded tenant initialization' => ['boutique.aydra.localhost', true, true, false],
]);

test('diagnostics require a local environment debug mode and a loopback peer', function (string $environment, bool $debug, string $ip, bool $allowed): void {
    $this->app['env'] = $environment;
    config(['app.debug' => $debug]);
    $request = Request::create('/_dev/database', server: ['REMOTE_ADDR' => $ip]);

    if (! $allowed) {
        expect(fn () => app(EnsureLocalDiagnostics::class)->handle($request, fn () => response('diagnostics')))
            ->toThrow(NotFoundHttpException::class);

        return;
    }

    expect(app(EnsureLocalDiagnostics::class)->handle($request, fn () => response('diagnostics'))->getContent())->toBe('diagnostics');
})->with([
    'IPv4 loopback' => ['local', true, '127.0.0.1', true],
    'IPv6 loopback' => ['local', true, '::1', true],
    'mapped loopback' => ['local', true, '::ffff:127.0.0.1', true],
    'production' => ['production', true, '127.0.0.1', false],
    'debug disabled' => ['local', false, '127.0.0.1', false],
    'remote address' => ['local', true, '192.0.2.10', false],
]);

test('forwarded loopback addresses cannot expose diagnostics through a trusted remote proxy', function (): void {
    $this->app['env'] = 'local';
    config(['app.debug' => true]);
    $proxies = Request::getTrustedProxies();
    $headers = Request::getTrustedHeaderSet();
    Request::setTrustedProxies(['192.0.2.10'], Request::HEADER_X_FORWARDED_FOR);
    $request = Request::create('/_dev/database', server: [
        'REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
    ]);

    try {
        expect(fn () => app(EnsureLocalDiagnostics::class)->handle($request, fn () => response('diagnostics')))
            ->toThrow(NotFoundHttpException::class);
    } finally {
        Request::setTrustedProxies($proxies, $headers);
    }
});
