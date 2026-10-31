<?php

namespace App\Http\Controllers\Central\Admin\Geography;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Geography\GeographyActionRequest;
use App\Http\Requests\Central\Geography\SearchGeographyRequest;
use App\Http\Requests\Central\Geography\StoreGeographicAreaRequest;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Services\Central\Geography\GeographicAreaService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class GeographyController extends Controller
{
    public function __construct(private GeographicAreaService $areas) {}

    public function index(SearchGeographyRequest $request): View
    {
        $filters = $request->validated();
        $query = GeographicArea::with(['country', 'parent'])->search($filters['search'] ?? null)
            ->when($filters['country_uuid'] ?? null, fn (Builder $q, $uuid) => $q->whereHas('country', fn (Builder $country) => $country->where('uuid', $uuid)))
            ->when($filters['parent_uuid'] ?? null, fn (Builder $q, $uuid) => $q->whereHas('parent', fn (Builder $parent) => $parent->where('uuid', $uuid)))
            ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type));
        match ($filters['state'] ?? 'all') {
            'active' => $query->active(),
            'inactive' => $query->where('is_active', false),
            'archived' => $query->onlyTrashed(),
            default => $query->withTrashed(),
        };

        return view('central.pages.admin.geography.index', [
            ...$this->options(), 'areas' => $query->orderBy('code')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        Gate::forUser($this->centralActor())->authorize('create', GeographicArea::class);

        return view('central.pages.admin.geography.create', [...$this->options(), 'area' => null]);
    }

    public function show(GeographicArea $area): View
    {
        Gate::forUser($this->centralActor())->authorize('view', $area);
        $area->load(['country', 'parent']);

        return view('central.pages.admin.geography.show', ['area' => $area, 'communes' => $area->children()->withTrashed()->orderBy('name_fr')->paginate(20)]);
    }

    public function edit(GeographicArea $area): View
    {
        Gate::forUser($this->centralActor())->authorize('update', $area);

        return view('central.pages.admin.geography.edit', [...$this->options(), 'area' => $area->load(['country', 'parent'])]);
    }

    public function store(StoreGeographicAreaRequest $request): RedirectResponse
    {
        $this->areas->save($this->centralActor(), $request->validated());

        return $this->redirect('Zone créée inactive. Activez-la pour la proposer dans les formulaires.');
    }

    public function update(StoreGeographicAreaRequest $request, GeographicArea $area): RedirectResponse
    {
        $this->areas->save($this->centralActor(), $request->validated(), $area);

        return $this->redirect('Zone enregistrée.');
    }

    public function activate(GeographyActionRequest $request, GeographicArea $area): RedirectResponse
    {
        $this->areas->setActive($this->centralActor(), $area, $request->boolean('is_active'), $request->validated('expected_state'));

        return $this->redirect('Disponibilité enregistrée. Une wilaya inactive masque ses communes dans les sélecteurs.');
    }

    public function archive(GeographyActionRequest $request, GeographicArea $area): RedirectResponse
    {
        $this->areas->archive($this->centralActor(), $area, $request->validated('expected_state'));

        return $this->redirect('Zone archivée.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return ['countries' => Country::orderBy('name_fr')->get(),
            'provinces' => GeographicArea::where('type', GeoZoneTypeEnum::PROVINCE)->orderBy('code')->get()];
    }

    private function redirect(string $message): RedirectResponse
    {
        $route = Gate::forUser($this->centralActor())->allows('viewAny', GeographicArea::class) ? 'central.geography.index' : 'dashboard';

        return redirect()->route($route)->with('status', $message);
    }
}
