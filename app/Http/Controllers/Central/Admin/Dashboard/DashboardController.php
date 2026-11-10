<?php

namespace App\Http\Controllers\Central\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Central\Users\User;
use App\Services\Central\Dashboard\AdminDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboard) {}

    public function __invoke(): View
    {
        $actor = $this->centralActor();
        $gate = Gate::forUser($actor);
        $general = $gate->allows('viewAdminDashboard', User::class);
        $financial = $gate->allows('viewFinancialDashboard', User::class);
        abort_unless($general || $financial, 403);

        return view('central.pages.admin.dashboard.index', [
            'overview' => $general ? $this->dashboard->overview($actor) : null,
            'payments' => $financial ? $this->dashboard->financial($actor) : null,
        ]);
    }
}
