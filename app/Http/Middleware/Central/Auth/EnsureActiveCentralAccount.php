<?php

namespace App\Http\Middleware\Central\Auth;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\User;
use App\Services\Central\Auth\RegistrationJourney;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCentralAccount
{
    public function __construct(private RegistrationJourney $journey) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        abort_if(tenancy()->initialized, 403);
        $this->checkAccount($request, $mode === 'optional');
        $response = $next($request);
        if ($mode === 'optional') {
            $this->checkAccount($request, true, true);
        }

        return $response;
    }

    private function checkAccount(Request $request, bool $allowGuest, bool $afterAuthentication = false): void
    {
        $user = $request->user('central');
        if ($user === null && $allowGuest) {
            return;
        }
        $user = $user?->fresh();
        if ($user instanceof User && $this->journey->mayAuthenticate($user)) {
            if ($user->status === StatusEnum::ACTIVE) {
                if (! $allowGuest && $request->routeIs('dashboard', '*.dashboard')) {
                    $destination = $this->journey->destination($user);
                    if ($destination !== route('dashboard')) {
                        if ($request->expectsJson() || ! $request->isMethod('GET')) {
                            abort(403);
                        }
                        throw new HttpResponseException(redirect()->to($destination));
                    }
                }
                return;
            }
            if ($request->routeIs('central.contacts.verify', '*.central.contacts.verify')) {
                return;
            }
            if ($allowGuest && $request->routeIs('login', 'login.store', 'register', 'register.store', 'logout', 'verification.*', 'registration.status', '*.registration.status')) {
                return;
            }
            if ($afterAuthentication) {
                $this->disconnect($request);
                abort(403);
            }
            if (! $request->expectsJson() && $request->isMethod('GET')) {
                throw new HttpResponseException(redirect()->to($this->journey->destination($user)));
            }
            abort(403);
        }
        if (! $user instanceof User || $user->status !== StatusEnum::ACTIVE) {
            $this->disconnect($request);
            abort(403);
        }
    }

    private function disconnect(Request $request): void
    {
        Auth::guard('central')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
