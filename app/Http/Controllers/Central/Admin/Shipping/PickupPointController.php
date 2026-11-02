<?php

namespace App\Http\Controllers\Central\Admin\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Shipping\ImportPickupPointRequest;
use App\Http\Requests\Central\Shipping\PickupPointActionRequest;
use App\Http\Requests\Central\Shipping\StorePickupPointRequest;
use App\Models\Central\Shipping\PickupPoint;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Services\Central\Shipping\PickupPointImportService;
use App\Services\Central\Shipping\PickupPointService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PickupPointController extends Controller
{
    private const string IMPORT_PREVIEW_SESSION = 'pickup_point_import_preview';

    public function __construct(
        private readonly PickupPointService $points,
        private readonly PickupPointImportService $imports,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', PickupPoint::class);

        $filters = $request->validate([
            'carrier_uuid' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'is_carrier_active' => ['nullable', 'boolean'],
            'archived' => ['nullable', 'boolean'],
        ]);

        return view('central.pages.admin.shipping.pickup-points.index', [
            'points' => $this->points->search($actor, $filters),
            'carriers' => $this->carriers(),
        ]);
    }

    public function create(): View
    {
        Gate::forUser($this->centralActor())->authorize('create', PickupPoint::class);

        return view('central.pages.admin.shipping.pickup-points.create', [
            'point' => null,
            'carriers' => $this->carriers(),
        ]);
    }

    public function store(StorePickupPointRequest $request): RedirectResponse
    {
        $this->points->save($this->centralActor(), $request->validated());

        return $this->redirectWithStatus('Bureau enregistré, inactif. Son activation nécessite le droit dédié.');
    }

    public function edit(PickupPoint $point): View
    {
        Gate::forUser($this->centralActor())->authorize('update', $point);

        return view('central.pages.admin.shipping.pickup-points.edit', [
            'point' => $point->load(['carrier', 'province', 'municipality']),
        ]);
    }

    public function update(StorePickupPointRequest $request, PickupPoint $point): RedirectResponse
    {
        $this->points->save(
            $this->centralActor(),
            $request->validated(),
            $point,
            $request->validated('expected_state'),
        );

        return $this->redirectWithStatus('Bureau mis à jour.');
    }

    public function show(PickupPoint $point): View
    {
        Gate::forUser($this->centralActor())->authorize('view', $point);

        return view('central.pages.admin.shipping.pickup-points.show', ['point' => $point->load(['carrier', 'province', 'municipality'])]);
    }

    public function activate(PickupPointActionRequest $request, PickupPoint $point): RedirectResponse
    {
        $this->points->setActive($this->centralActor(), $point, $request->boolean('is_carrier_active'), $request->validated('expected_state'));

        return $this->redirectWithStatus('Disponibilité du bureau mise à jour.');
    }

    public function archive(PickupPointActionRequest $request, PickupPoint $point): RedirectResponse
    {
        $this->points->archive($this->centralActor(), $point, $request->validated('expected_state'));

        return $this->redirectWithStatus('Bureau archivé. Son identité et son historique sont conservés.');
    }

    public function import(Request $request): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('import', PickupPoint::class);

        $token = $request->session()->get(self::IMPORT_PREVIEW_SESSION);

        return view('central.pages.admin.shipping.pickup-points.import', [
            'carriers' => $this->carriers(),
            'preview' => is_string($token) ? $this->imports->review($actor, $token) : null,
        ]);
    }

    public function preview(ImportPickupPointRequest $request): RedirectResponse
    {
        try {
            $preview = $this->imports->preview(
                $this->centralActor(),
                $request->validated('carrier_uuid'),
                $request->file('file'),
            );
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('central.pickup-points.import'));
        }

        $request->session()->put(self::IMPORT_PREVIEW_SESSION, $preview['token']);

        return to_route('central.pickup-points.import');
    }

    public function confirm(ImportPickupPointRequest $request): RedirectResponse
    {
        try {
            $result = $this->imports->confirm($this->centralActor(), $request->validated('token'));
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('central.pickup-points.import'));
        }

        $request->session()->forget(self::IMPORT_PREVIEW_SESSION);

        return $this->redirectWithStatus(sprintf(
            'Import confirmé : %d créée(s), %d modifiée(s), %d inchangée(s).',
            $result['created'],
            $result['updated'],
            $result['unchanged'],
        ));
    }

    /** @return Collection<int, ShippingCarrier> */
    private function carriers(): Collection
    {
        return ShippingCarrier::query()->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    /** Redirige vers la liste si l'acteur peut la voir, sinon vers le tableau de bord. */
    private function redirectWithStatus(string $message): RedirectResponse
    {
        $redirect = Gate::forUser($this->centralActor())->allows('viewAny', PickupPoint::class)
            ? to_route('central.pickup-points.index')
            : to_route('dashboard');

        return $redirect->with('status', $message);
    }
}
