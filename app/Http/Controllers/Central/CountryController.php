<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ChangeCountryStatusRequest;
use App\Http\Requests\Central\StoreCountryRequest;
use App\Models\Central\Country;
use App\Models\Central\User;
use App\Services\Central\CountryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CountryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Country::class);

        return view('central.pages.countries.index', ['countries' => Country::orderBy('name_fr')->orderBy('id')->paginate(20)]);
    }

    public function create(): View
    {
        Gate::authorize('create', Country::class);

        return view('central.pages.countries.create', ['country' => null]);
    }

    public function edit(Country $country): View
    {
        Gate::authorize('update', $country);

        return view('central.pages.countries.edit', compact('country'));
    }

    public function store(StoreCountryRequest $request, CountryService $countries): RedirectResponse
    {
        $countries->save($this->actor($request), $request->validated());

        return redirect()->route('central.countries.index')->with('status', 'Pays créé. Activez-le pour permettre sa sélection.');
    }

    public function update(StoreCountryRequest $request, Country $country, CountryService $countries): RedirectResponse
    {
        $countries->save($this->actor($request), $request->validated(), $country);

        return redirect()->route('central.countries.index')->with('status', 'Pays enregistré.');
    }

    public function activate(ChangeCountryStatusRequest $request, Country $country, CountryService $countries): RedirectResponse
    {
        Gate::authorize('activate', $country);
        $data = $request->validated();
        $countries->setActive($this->actor($request), $country, (bool) $data['is_active']);

        return redirect()->route('central.countries.index')->with('status', 'Disponibilité du pays enregistrée.');
    }

    public function destroy(Request $request, Country $country, CountryService $countries): RedirectResponse
    {
        $countries->delete($this->actor($request), $country);

        return redirect()->route('central.countries.index')->with('status', 'Pays retiré.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
