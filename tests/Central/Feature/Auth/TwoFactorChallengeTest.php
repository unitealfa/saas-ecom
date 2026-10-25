<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));
});

test('two factor limits do not share a fallback key between different clients', function (): void {
    $limiter = RateLimiter::limiter('two-factor');
    $first = Request::create('/two-factor-challenge', 'POST', server: ['REMOTE_ADDR' => '192.0.2.10']);
    $first->setLaravelSession(app('session')->driver());
    $second = Request::create('/two-factor-challenge', 'POST', server: ['REMOTE_ADDR' => '192.0.2.20']);
    $second->setLaravelSession(app('session')->driver());

    expect($limiter($first)->key)->not->toBeEmpty()->not->toBe($limiter($second)->key);
});

test('two factor limits distinguish challenged accounts and do not collide with IP fallback keys', function (): void {
    $limiter = RateLimiter::limiter('two-factor');
    $request = Request::create('/two-factor-challenge', 'POST', server: ['REMOTE_ADDR' => '192.0.2.10']);
    $request->setLaravelSession(app('session')->driver());
    $request->session()->put('login.id', 17);
    $firstKey = $limiter($request)->key;
    $request->session()->put('login.id', 18);
    $secondKey = $limiter($request)->key;
    $request->session()->forget('login.id');

    expect($firstKey)->not->toBe($secondKey)->not->toBe($limiter($request)->key);
});
