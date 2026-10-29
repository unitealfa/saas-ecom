<?php

namespace App\Http\Middleware\Central\Auth;

use App\Enums\Central\Users\JourneyState;
use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Users\User;
use App\Services\Central\Auth\RegistrationJourney;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCentralAccount
{
    private const GUARD = 'central';

    private const LIMITED_ACCESS_ROUTES = [
        'login', 'login.store', 'register', 'register.store', 'logout',
        'verification.*', 'registration.status', '*.registration.status',
    ];

    public function __construct(private RegistrationJourney $journey) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        abort_if(tenancy()->initialized, 403);

        $optional = $mode === 'optional';
        $this->checkAccount($request, $optional);
        $response = $next($request);

        if ($optional) {
            $this->checkAccount($request, true, true);
        }

        return $response;
    }

    private function checkAccount(Request $request, bool $allowGuest, bool $afterAuthentication = false): void
    {
        $user = $request->user(self::GUARD);

        if ($user === null && $allowGuest) {
            return;
        }

        $user = $user?->fresh();

        if (! $user instanceof User || ! $this->journey->mayAuthenticate($user)) {
            $this->reject($request);
        }

        if (! $allowGuest && in_array($request->method(), ['GET', 'HEAD'], true)) {
            Auth::guard(self::GUARD)->setUser($user);
            $request->attributes->set(User::AUTHORIZATION_READ_USER, $user);
        }

        if ($user->status === StatusEnum::ACTIVE) {
            if (! $allowGuest && $request->routeIs('dashboard', '*.dashboard')) {
                $this->ensureJourneyCompleted($request, $user);
            }

            return;
        }

        if ($allowGuest && $request->routeIs(...self::LIMITED_ACCESS_ROUTES)) {
            return;
        }

        if ($afterAuthentication) {
            $this->reject($request);
        }

        $this->redirectOrDeny($request, $user);
    }

    private function ensureJourneyCompleted(Request $request, User $user): void
    {
        $state = $this->journey->state($user);

        if ($state !== JourneyState::READY) {
            $this->redirectOrDeny($request, $user, route($state->routeName()));
        }
    }

    private function redirectOrDeny(Request $request, User $user, ?string $destination = null): never
    {
        if ($request->expectsJson() || ! $request->isMethod('GET')) {
            abort(403);
        }

        throw new HttpResponseException(
            redirect()->to($destination ?? $this->journey->destination($user))
        );
    }

    private function reject(Request $request): never
    {
        $this->disconnect($request);
        abort(403);
    }

    private function disconnect(Request $request): void
    {
        Auth::guard(self::GUARD)->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
