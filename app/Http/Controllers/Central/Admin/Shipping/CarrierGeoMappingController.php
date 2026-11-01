<?php

namespace App\Http\Controllers\Central\Admin\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Shipping\CarrierGeoMappingActionRequest;
use App\Http\Requests\Central\Shipping\ImportCarrierGeoMappingRequest;
use App\Http\Requests\Central\Shipping\StoreCarrierGeoMappingRequest;
use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Services\Central\Shipping\CarrierGeoImportService;
use App\Services\Central\Shipping\CarrierGeoMappingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CarrierGeoMappingController extends Controller
{
    private const string IMPORT_PREVIEW_SESSION = 'carrier_geo_import_preview';

    public function __construct(
        private readonly CarrierGeoMappingService $mappings,
        private readonly CarrierGeoImportService $imports,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', CarrierGeoMapping::class);
        $filters = $request->validate(['carrier_uuid' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'], 'is_active' => ['nullable', 'boolean']]);

        return view('central.pages.admin.shipping.geo-mappings.index', [
            'mappings' => $this->mappings->search($actor, $filters), 'carriers' => $this->carriers(),
        ]);
    }

    public function create(): View
    {
        Gate::forUser($this->centralActor())->authorize('create', CarrierGeoMapping::class);

        return view('central.pages.admin.shipping.geo-mappings.create', ['mapping' => null, 'carriers' => $this->carriers()]);
    }

    public function store(StoreCarrierGeoMappingRequest $request): RedirectResponse
    {
        $this->mappings->save($this->centralActor(), $request->validated());

        return $this->redirectWithStatus('Correspondance enregistrée et vérifiée selon la source renseignée.');
    }

    public function edit(CarrierGeoMapping $mapping): View
    {
        Gate::forUser($this->centralActor())->authorize('update', $mapping);
        $mapping->load(['carrier', 'area']);

        return view('central.pages.admin.shipping.geo-mappings.edit', compact('mapping'));
    }

    public function update(StoreCarrierGeoMappingRequest $request, CarrierGeoMapping $mapping): RedirectResponse
    {
        $this->mappings->save($this->centralActor(), $request->validated(), $mapping, $request->validated('expected_state'));

        return $this->redirectWithStatus('Correspondance mise à jour.');
    }

    public function archive(CarrierGeoMappingActionRequest $request, CarrierGeoMapping $mapping): RedirectResponse
    {
        $this->mappings->archive($this->centralActor(), $mapping, $request->validated('expected_state'));

        return $this->redirectWithStatus('Correspondance archivée. Son identité et son historique sont conservés.');
    }

    public function import(Request $request): View
    {
        Gate::forUser($this->centralActor())->authorize('import', CarrierGeoMapping::class);
        $token = $request->session()->get(self::IMPORT_PREVIEW_SESSION);
        $preview = is_string($token) ? $this->imports->review($this->centralActor(), $token) : null;

        return view('central.pages.admin.shipping.geo-mappings.import', ['carriers' => $this->carriers(), 'preview' => $preview]);
    }

    public function preview(ImportCarrierGeoMappingRequest $request): RedirectResponse
    {
        try {
            $preview = $this->imports->preview($this->centralActor(), $request->validated('carrier_uuid'), $request->file('file'));
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('central.carrier-mappings.import'));
        }
        $request->session()->put(self::IMPORT_PREVIEW_SESSION, $preview['token']);

        return to_route('central.carrier-mappings.import');
    }

    public function confirm(ImportCarrierGeoMappingRequest $request): RedirectResponse
    {
        try {
            $result = $this->imports->confirm($this->centralActor(), $request->validated('token'));
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('central.carrier-mappings.import'));
        }
        $request->session()->forget(self::IMPORT_PREVIEW_SESSION);

        return $this->redirectWithStatus('Import confirmé : '.$result['created'].' créée(s), '
            .$result['updated'].' modifiée(s), '.$result['unchanged'].' inchangée(s).');
    }

    /** @return Collection<int, ShippingCarrier> */
    private function carriers(): Collection
    {
        return ShippingCarrier::query()->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    private function redirectWithStatus(string $message): RedirectResponse
    {
        $response = Gate::forUser($this->centralActor())->allows('viewAny', CarrierGeoMapping::class)
            ? to_route('central.carrier-mappings.index') : to_route('dashboard');

        return $response->with('status', $message);
    }
}
