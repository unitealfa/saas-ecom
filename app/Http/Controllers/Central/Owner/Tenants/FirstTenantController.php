<?php

namespace App\Http\Controllers\Central\Owner\Tenants;

use App\DTOs\Tenant\Shop\ShopSettings;
use App\Enums\Central\Users\JourneyState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Tenants\StoreFirstTenantRequest;
use App\Models\Central\User;
use App\Services\Central\Auth\RegistrationJourney;
use App\Services\Central\Tenants\TenantCreator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FirstTenantController extends Controller
{
    public function create(Request $request, RegistrationJourney $journey): View|RedirectResponse
    {
        $owner = $this->centralActor()->fresh();
        abort_unless($owner instanceof User, 403);
        $state = $journey->state($owner);

        if ($state !== JourneyState::SETUP) {
            return redirect()->route($state->routeName());
        }

        if (! $request->session()->has('first_shop_creation_key')) {
            $request->session()->put('first_shop_creation_key', (string) Str::uuid());
        }

        return view('central.pages.owner.tenants.first-shop', [
            'settings' => ShopSettings::fromArray()->toArray(),
        ]);
    }

    public function store(StoreFirstTenantRequest $request, TenantCreator $creator): RedirectResponse
    {
        $creator->createFirst($this->centralActor(), $request->toDto());

        return redirect()->route('registration.status')
            ->with('status', 'Votre boutique a été enregistrée. Sa préparation technique est en cours.');
    }
}
