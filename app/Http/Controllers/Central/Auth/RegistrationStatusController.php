<?php

namespace App\Http\Controllers\Central\Auth;

use App\Enums\Central\Users\JourneyState;
use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Services\Central\Auth\RegistrationJourney;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationStatusController extends Controller
{
    private const array NO_STORE = ['Cache-Control' => 'private, no-store'];

    public function __invoke(Request $request, RegistrationJourney $journey): Response
    {
        $user = $request->user('central')?->fresh();
        abort_unless($user instanceof User, 403);

        $progress = $journey->progress($user);

        if ($request->expectsJson()) {
            return response()->json($progress, 200, self::NO_STORE);
        }

        $state = JourneyState::from($progress['state']);
        if (! in_array($state, [JourneyState::PENDING, JourneyState::REJECTED, JourneyState::PREPARING], true)) {
            return redirect()->route($state->routeName());
        }

        return response()->view(
            'central.pages.auth.registration-status',
            ['user' => $user, 'progress' => $progress],
            200,
            self::NO_STORE,
        );
    }
}
