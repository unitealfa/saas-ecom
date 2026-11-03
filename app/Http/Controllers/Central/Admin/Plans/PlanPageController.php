<?php

namespace App\Http\Controllers\Central\Admin\Plans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Plans\ArchivePlanRequest;
use App\Http\Requests\Central\Plans\SearchPlansRequest;
use App\Http\Requests\Central\Plans\StorePlanRequest;
use App\Http\Requests\Central\Plans\StorePlanVersionRequest;
use App\Http\Requests\Central\Plans\UpdatePlanActivationRequest;
use App\Http\Requests\Central\Plans\UpdatePlanPageFeaturesRequest;
use App\Http\Requests\Central\Plans\UpdatePlanRequest;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Services\Central\Plans\PlanActivationService;
use App\Services\Central\Plans\PlanArchiveService;
use App\Services\Central\Plans\PlanCatalogService;
use App\Services\Central\Plans\PlanCompositionService;
use App\Services\Central\Plans\PlanCreationService;
use App\Services\Central\Plans\PlanUpdateService;
use App\Services\Central\Plans\PlanVersionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PlanPageController extends Controller
{
    public function __construct(
        private readonly PlanCatalogService $plans,
        private readonly PlanCreationService $creation,
        private readonly PlanUpdateService $update,
        private readonly PlanCompositionService $composition,
        private readonly PlanVersionService $versions,
        private readonly PlanActivationService $activation,
        private readonly PlanArchiveService $archive,
    ) {}

    public function index(SearchPlansRequest $request): View
    {
        return view('central.pages.admin.plans.index', [
            'plans' => $this->plans->paginate($this->centralActor(), $request->validated('search')),
        ]);
    }

    public function create(): View
    {
        Gate::forUser($this->centralActor())->authorize('create', Plan::class);

        return view('central.pages.admin.plans.create');
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $plan = $this->creation->create($this->centralActor(), $request->validated());

        return $this->redirectWithStatus($plan, 'Plan créé en version inactive.');
    }

    public function show(Plan $plan): View
    {
        $actor = $this->centralActor();
        $plan = $this->plans->details($actor, $plan);
        $availableFeatures = Gate::forUser($actor)->allows('updateFeatures', $plan)
            ? Feature::query()->active()->orderBy('name')->get()
            : collect();

        return view('central.pages.admin.plans.show', [
            'plan' => $plan,
            'latestVersion' => $this->plans->latestVersion($actor, $plan),
            'availableFeatures' => $availableFeatures,
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->update->update($this->centralActor(), $plan, $request->validated());

        return $this->redirectWithStatus($plan, 'Informations enregistrées.');
    }

    public function updateFeatures(UpdatePlanPageFeaturesRequest $request, Plan $plan): RedirectResponse
    {
        $this->composition->update($this->centralActor(), $plan, $request->composition());

        return $this->redirectWithStatus($plan, 'Fonctionnalités et quotas enregistrés.');
    }

    public function storeVersion(StorePlanVersionRequest $request, Plan $plan): RedirectResponse
    {
        $version = $this->versions->create($this->centralActor(), $plan, $request->validated());

        return $this->redirectWithStatus($version, 'Nouvelle version inactive créée.');
    }

    public function activate(UpdatePlanActivationRequest $request, Plan $plan): RedirectResponse
    {
        $this->activation->setActive($this->centralActor(), $plan, $request->validated());

        return $this->redirectWithStatus($plan, 'Commercialisation mise à jour.');
    }

    public function archive(ArchivePlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->archive->archive($this->centralActor(), $plan, $request->validated());

        return $this->redirectWithStatus($plan, 'Version archivée.');
    }

    private function redirectWithStatus(Plan $plan, string $message): RedirectResponse
    {
        return (Gate::forUser($this->centralActor())->allows('view', $plan)
            ? to_route('central.plan-pages.show', $plan)
            : to_route('dashboard'))->with('status', $message);
    }
}
