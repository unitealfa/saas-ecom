<?php

namespace App\Http\Controllers\Central\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Central\Auth\RegistrationJourney;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, RegistrationJourney $journey): View|RedirectResponse
    {
        $destination = $journey->destination($request->user('central')->fresh());

        return $destination === route('dashboard')
            ? view('central.dashboard')
            : redirect()->to($destination);
    }
}
