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
    private const int PER_PAGE = 20;

    public function __construct(private readonly GeographicAreaService $areas) {}

    public function index(SearchGeographyRequest $request): View
    {
        $filters = $request->validated();

        $query = GeographicArea::with(['country', 'parent'])
            ->search($filters['search'] ?? null)
            ->when($filters['country_uuid'] ?? null, fn (Builder $q, string $uuid) => $q->whereRelation('country', 'uuid', $uuid))
            ->when($filters['parent_uuid'] ?? null, fn (Builder $q, string $uuid) => $q->whereRelation('parent', 'uuid', $uuid))
            ->when($filters['type'] ?? null, fn (Builder $q, mixed $type) => $q->where('type', $type));

        $query = match ($filters['state'] ?? 'all') {
            'active' => $query->active(),
            'inactive' => $query->where('is_active', false),
            'archived' => $query->onlyTrashed(),
            default => $query->withTrashed(),
        };

        return view('central.pages.admin.geography.index', [
            ...$this->options(),
            'areas' => $query->orderBy('code')->paginate(self::PER_PAGE)->withQueryString(),
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

        return view('central.pages.admin.geography.show', [
            'area' => $area->load(['country', 'parent']),
            'communes' => $area->children()->withTrashed()->orderBy('name_fr')->paginate(self::PER_PAGE),
        ]);
    }

    public function edit(GeographicArea $area): View
    {
        Gate::forUser($this->centralActor())->authorize('update', $area);

        return view('central.pages.admin.geography.edit', [
            'area' => $area->load(['country', 'parent']),
        ]);
    }

    public function store(StoreGeographicAreaRequest $request): RedirectResponse
    {
        $this->areas->save($this->centralActor(), $request->validated());

        return $this->redirectWithStatus('Zone créée inactive. Activez-la pour la proposer dans les formulaires.');
    }

    public function update(StoreGeographicAreaRequest $request, GeographicArea $area): RedirectResponse
    {
        $this->areas->save($this->centralActor(), $request->validated(), $area);

        return $this->redirectWithStatus('Zone enregistrée.');
    }

    public function activate(GeographyActionRequest $request, GeographicArea $area): RedirectResponse
    {
        $this->areas->setActive(
            $this->centralActor(),
            $area,
            $request->boolean('is_active'),
            $request->validated('expected_state'),
        );

        return $this->redirectWithStatus('Disponibilité enregistrée. Une wilaya inactive masque ses communes dans les sélecteurs.');
    }

    public function archive(GeographyActionRequest $request, GeographicArea $area): RedirectResponse
    {
        $this->areas->archive($this->centralActor(), $area, $request->validated('expected_state'));

        return $this->redirectWithStatus('Zone archivée.');
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'countries' => Country::orderBy('name_fr')->get(),
            'provinces' => GeographicArea::where('type', GeoZoneTypeEnum::PROVINCE)->orderBy('code')->get(),
        ];
    }

    /** Redirige vers la liste si l'acteur peut la voir, sinon vers le tableau de bord. */
    private function redirectWithStatus(string $message): RedirectResponse
    {
        $redirect = Gate::forUser($this->centralActor())->allows('viewAny', GeographicArea::class)
            ? to_route('central.geography.index')
            : to_route('dashboard');

        return $redirect->with('status', $message);
    }
}
