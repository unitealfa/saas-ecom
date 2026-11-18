<?php

namespace App\Http\Controllers\Central\Admin\Plans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Plans\ActivateFreePlanTeamQuotaRequest;
use App\Http\Requests\Central\Plans\SaveFreePlanTeamQuotaRequest;
use App\Models\Central\Plans\Plan;
use App\Services\Central\Plans\FreePlanTeamQuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class FreePlanTeamQuotaController extends Controller
{
    public function store(
        SaveFreePlanTeamQuotaRequest $request,
        Plan $plan,
        FreePlanTeamQuotaService $service,
    ): RedirectResponse {
        $draft = $service->update($this->centralActor(), $plan, $request->validated());

        return $this->redirect($draft, 'Version gratuite préparée. Son activation reste une action distincte.');
    }

    public function activate(
        ActivateFreePlanTeamQuotaRequest $request,
        Plan $plan,
        FreePlanTeamQuotaService $service,
    ): RedirectResponse {
        $activated = $service->activate(
            $this->centralActor(), $plan, $request->validated('expected_state'),
        );

        return $this->redirect($activated, 'Nouvelle version gratuite activée. Les abonnements historiques sont conservés.');
    }

    private function redirect(Plan $plan, string $message): RedirectResponse
    {
        return (Gate::forUser($this->centralActor())->allows('view', $plan)
            ? to_route('central.plan-pages.show', $plan)
            : to_route('dashboard'))->with('status', $message);
    }


}
