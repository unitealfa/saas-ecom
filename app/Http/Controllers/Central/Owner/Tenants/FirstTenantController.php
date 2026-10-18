<?php

namespace App\Http\Controllers\Central\Owner\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Tenants\StoreFirstTenantRequest;
use App\Services\Central\Auth\RegistrationJourney;
use App\Services\Central\Tenants\TenantCreator;
use App\DTOs\Tenant\Shop\ShopSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FirstTenantController extends Controller
{
    public function create(Request $request, RegistrationJourney $journey): View|RedirectResponse
    {
        $destination = $journey->destination($request->user('central')->fresh());
        if ($destination !== route('owner.first-shop')) {
            return redirect()->to($destination);
        }
        if (! $request->session()->has('first_shop_creation_key')) {
            $request->session()->put('first_shop_creation_key', (string) Str::uuid());
        }

        return view('central.pages.owner.tenants.first-shop', ['settings' => ShopSettings::fromArray()->toArray()]);
    }

    public function store(StoreFirstTenantRequest $request, TenantCreator $creator): RedirectResponse
    {
        $creator->createFirst($request->user('central'), $request->toDto());

        return redirect()->route('registration.status')->with('status', 'Votre boutique a été enregistrée. Sa préparation technique est en cours.');
    }
}
