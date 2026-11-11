<?php

namespace App\Http\Controllers\Central\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Central\Users\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user('central');
        abort_unless($user instanceof User, 403);

        $gate = Gate::forUser($user);
        if ($gate->allows('viewAdminDashboard', \App\Models\User::class)
            || $gate->allows('viewFinancialDashboard', \App\Models\User::class)) {
            $request->session()->reflash();

            return redirect()->route('central.admin-dashboard.index');
        }

        return view('central.dashboard');
    }
}
