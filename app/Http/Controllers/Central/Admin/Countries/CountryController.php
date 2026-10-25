<?php

namespace App\Http\Controllers\Central\Admin\Countries;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Countries\ChangeCountryStatusRequest;
use App\Http\Requests\Central\Countries\StoreCountryRequest;
use App\Models\Central\Country;
use App\Models\Central\User;
use App\Services\Central\Countries\CountryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CountryController extends Controller
{
    public function __construct(private CountryService $countries) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Country::class);

        return view('central.pages.admin.countries.index', [
            'countries' => Country::orderBy('name_fr')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Country::class);

        return view('central.pages.admin.countries.create', ['country' => null]);
    }

    public function edit(Country $country): View
    {
        Gate::authorize('update', $country);

        return view('central.pages.admin.countries.edit', compact('country'));
    }

    public function store(StoreCountryRequest $request): RedirectResponse
    {
        $this->countries->save($this->actor(), $request->validated());

        return $this->back('Pays créé. Activez-le pour permettre sa sélection.');
    }

    public function update(StoreCountryRequest $request, Country $country): RedirectResponse
    {
        $this->countries->save($this->actor(), $request->validated(), $country);

        return $this->back('Pays enregistré.');
    }

    public function activate(ChangeCountryStatusRequest $request, Country $country): RedirectResponse
    {
        Gate::authorize('activate', $country);
        $this->countries->setActive($this->actor(), $country, $request->boolean('is_active'));

        return $this->back('Disponibilité du pays enregistrée.');
    }

    public function destroy(Country $country): RedirectResponse
    {
        Gate::authorize('delete', $country);
        $this->countries->delete($this->actor(), $country);

        return $this->back('Pays retiré.');
    }

    private function back(string $status): RedirectResponse
    {
        return redirect()->route('central.countries.index')->with('status', $status);
    }

    private function actor(): User
    {
        $actor = auth('central')->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}