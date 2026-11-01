<?php

namespace App\Http\Controllers\Central\Admin\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Shipping\ShippingCarrierActionRequest;
use App\Http\Requests\Central\Shipping\StoreShippingCarrierRequest;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Services\Central\Shipping\ShippingCarrierService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShippingCarrierController extends Controller
{
    public function __construct(private readonly ShippingCarrierService $carriers) {}

    public function index(Request $request): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', ShippingCarrier::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'], 'archived' => ['nullable', 'boolean'],
        ]);

        return view('central.pages.admin.shipping.carriers.index', ['carriers' => $this->carriers->search($actor, $filters)]);
    }

    public function create(): View
    {
        Gate::forUser($this->centralActor())->authorize('create', ShippingCarrier::class);

        return view('central.pages.admin.shipping.carriers.create', ['carrier' => null]);
    }

    public function store(StoreShippingCarrierRequest $request): RedirectResponse
    {
        $carrier = $this->carriers->create($this->centralActor(), $request->validated('code'), $request->validated('name'));

        return $this->redirectWithStatus($carrier, 'Transporteur ajouté inactif. Vous pouvez l’activer avec le droit dédié.');
    }

    public function show(ShippingCarrier $carrier): View
    {
        Gate::forUser($this->centralActor())->authorize('view', $carrier);

        return view('central.pages.admin.shipping.carriers.show', compact('carrier'));
    }

    public function edit(ShippingCarrier $carrier): View
    {
        Gate::forUser($this->centralActor())->authorize('update', $carrier);

        return view('central.pages.admin.shipping.carriers.edit', compact('carrier'));
    }

    public function update(StoreShippingCarrierRequest $request, ShippingCarrier $carrier): RedirectResponse
    {
        $this->carriers->update($this->centralActor(), $carrier, $request->validated('name'), $request->validated('expected_state'));

        return $this->redirectWithStatus($carrier, 'Transporteur enregistré.');
    }

    public function activate(ShippingCarrierActionRequest $request, ShippingCarrier $carrier): RedirectResponse
    {
        $this->carriers->setActive($this->centralActor(), $carrier, $request->boolean('is_active'), $request->validated('expected_state'));

        return $this->redirectWithStatus($carrier, 'Disponibilité du transporteur enregistrée.');
    }

    public function archive(ShippingCarrierActionRequest $request, ShippingCarrier $carrier): RedirectResponse
    {
        $this->carriers->archive($this->centralActor(), $carrier, $request->validated('expected_state'));

        return $this->redirectWithStatus($carrier, 'Transporteur archivé. Son code reste réservé.');
    }

    private function redirectWithStatus(ShippingCarrier $carrier, string $message): RedirectResponse
    {
        return (Gate::forUser($this->centralActor())->allows('view', $carrier)
            ? to_route('central.carriers.show', $carrier)
            : to_route('dashboard'))->with('status', $message);
    }
}
