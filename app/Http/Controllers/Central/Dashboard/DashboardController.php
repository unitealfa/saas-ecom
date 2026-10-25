<?php

namespace App\Http\Controllers\Central\Dashboard;

use App\Enums\Central\Users\JourneyState;
use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Services\Central\Auth\RegistrationJourney;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, RegistrationJourney $journey): View|RedirectResponse
    {
        $user = $request->user('central')?->fresh();
        abort_unless($user instanceof User, 403);

        $state = $journey->state($user);

        return $state === JourneyState::READY
            ? view('central.dashboard')
            : redirect()->route($state->routeName());
    }
}