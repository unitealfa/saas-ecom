<?php

namespace App\Http\Controllers\Central\Admin\Features;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Plans\FeatureActionRequest;
use App\Http\Requests\Central\Plans\StoreFeatureRequest;
use App\Models\Central\Features\Feature;
use App\Services\Central\Plans\FeatureCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FeatureController extends Controller
{
    public function __construct(private FeatureCatalogService $features) {}

    public function index(Request $request): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', Feature::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'archived' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return view('central.pages.admin.features.index', ['features' => $this->features->search($actor, $filters)]);
    }

    public function create(): View
    {
        Gate::forUser($this->centralActor())->authorize('create', Feature::class);

        return view('central.pages.admin.features.create', ['feature' => null, 'referenced' => false]);
    }

    public function store(StoreFeatureRequest $request): RedirectResponse
    {
        $feature = $this->features->create($this->centralActor(), $request->featureData());

        return $this->result($feature, 'Fonctionnalité créée inactive. Son activation nécessite l’autorisation dédiée.');
    }

    public function show(Feature $feature): View
    {
        $references = $this->features->references($this->centralActor(), $feature);

        return view('central.pages.admin.features.show', compact('feature', 'references'));
    }

    public function edit(Feature $feature): View
    {
        $referenced = $this->features->configurationLocked($this->centralActor(), $feature);

        return view('central.pages.admin.features.edit', compact('feature', 'referenced'));
    }

    public function update(StoreFeatureRequest $request, Feature $feature): RedirectResponse
    {
        $this->features->update($this->centralActor(), $feature, $request->featureData(), $request->validated('expected_state'));

        return $this->result($feature, 'Fonctionnalité enregistrée.');
    }

    public function activate(FeatureActionRequest $request, Feature $feature): RedirectResponse
    {
        $this->features->setActive($this->centralActor(), $feature, $request->boolean('is_active'), $request->validated('expected_state'));

        return $this->result($feature, 'État de la fonctionnalité enregistré.');
    }

    public function archive(FeatureActionRequest $request, Feature $feature): RedirectResponse
    {
        $this->features->archive($this->centralActor(), $feature, $request->validated('expected_state'));

        return $this->result($feature, 'Fonctionnalité archivée. Son code reste réservé.');
    }

    private function result(Feature $feature, string $message): RedirectResponse
    {
        $canView = Gate::forUser($this->centralActor())->allows('view', $feature);

        return redirect()->route($canView ? 'central.features.show' : 'dashboard', $canView ? $feature : [])->with('status', $message);
    }
}
