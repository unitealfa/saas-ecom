<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Services\Tenant\Dashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Tenancy $tenancy, Dashboard $dashboard): View
    {
        abort_unless($tenancy->initialized, 403);

        $user = $request->user('tenant');

        abort_unless($user instanceof User, 401);

        return view('tenant.pages.dashboard', ['dashboard' => $dashboard->forUser($user)]);
    }
}
