<?php

namespace App\Http\Controllers\Central\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Central\Users\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user('central');
        abort_unless($user instanceof User, 403);

        return view('central.dashboard');
    }
}
