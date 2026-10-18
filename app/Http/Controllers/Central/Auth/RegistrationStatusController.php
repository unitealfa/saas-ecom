<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Services\Central\Auth\RegistrationJourney;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationStatusController extends Controller
{
    public function __invoke(Request $request, RegistrationJourney $journey): Response
    {
        $user = $request->user('central')->fresh();
        $progress = $journey->progress($user);
        if ($request->expectsJson()) {
            return response()->json($progress)->header('Cache-Control', 'private, no-store');
        }

        return $progress['redirect'] === route('registration.status')
            ? response()->view('central.pages.auth.registration-status', ['user' => $user, 'progress' => $progress])
                ->header('Cache-Control', 'private, no-store')
            : redirect()->to($progress['redirect']);
    }
}
