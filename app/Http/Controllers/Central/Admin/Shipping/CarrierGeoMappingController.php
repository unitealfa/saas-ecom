<?php

namespace App\Http\Controllers\Central\Admin\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Shipping\CarrierGeoMappingActionRequest;
use App\Http\Requests\Central\Shipping\StoreCarrierGeoMappingRequest;
use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Services\Central\Shipping\CarrierGeoMappingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CarrierGeoMappingController extends Controller
{
    public function __construct(
        private readonly CarrierGeoMappingService $mappings,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', CarrierGeoMapping::class);

        $filters = $request->validate([
            'carrier_uuid' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return view('central.pages.admin.shipping.geo-mappings.index', [
            'mappings' => $this->mappings->search($actor, $filters),
            'carriers' => $this->carriers(),
        ]);
    }

    public function create(): View
    {
        Gate::forUser($this->centralActor())->authorize('create', CarrierGeoMapping::class);

        return view('central.pages.admin.shipping.geo-mappings.create', [
            'mapping' => null,
            'carriers' => $this->carriers(),
        ]);
    }

    public function store(StoreCarrierGeoMappingRequest $request): RedirectResponse
    {
        $this->mappings->save($this->centralActor(), $request->validated());

        return $this->redirectWithStatus('Correspondance enregistrée et vérifiée selon la source renseignée.');
    }

    public function edit(CarrierGeoMapping $mapping): View
    {
        Gate::forUser($this->centralActor())->authorize('update', $mapping);

        return view('central.pages.admin.shipping.geo-mappings.edit', [
            'mapping' => $mapping->load(['carrier', 'area']),
        ]);
    }

    public function update(StoreCarrierGeoMappingRequest $request, CarrierGeoMapping $mapping): RedirectResponse
    {
        $this->mappings->save(
            $this->centralActor(),
            $request->validated(),
            $mapping,
            $request->validated('expected_state'),
        );

        return $this->redirectWithStatus('Correspondance mise à jour.');
    }

    public function archive(CarrierGeoMappingActionRequest $request, CarrierGeoMapping $mapping): RedirectResponse
    {
        $this->mappings->archive($this->centralActor(), $mapping, $request->validated('expected_state'));

        return $this->redirectWithStatus('Correspondance archivée. Son identité et son historique sont conservés.');
    }

    /** @return Collection<int, ShippingCarrier> */
    private function carriers(): Collection
    {
        return ShippingCarrier::query()->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    /** Redirige vers la liste si l'acteur peut la voir, sinon vers le tableau de bord. */
    private function redirectWithStatus(string $message): RedirectResponse
    {
        $redirect = Gate::forUser($this->centralActor())->allows('viewAny', CarrierGeoMapping::class)
            ? to_route('central.carrier-mappings.index')
            : to_route('dashboard');

        return $redirect->with('status', $message);
    }
}
