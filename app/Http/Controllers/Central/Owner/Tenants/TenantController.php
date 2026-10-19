<?php

namespace App\Http\Controllers\Central\Owner\Tenants;

use App\DTOs\Tenant\Shop\ShopSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Tenants\StoreOwnedTenantRequest;
use App\Models\Central\Tenant;
use App\Services\Central\Tenants\TenantCreator;
use App\Services\Central\Tenants\TenantProvisioningRetry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $owner = $request->user('central')->fresh();
        Gate::forUser($owner)->authorize('createOwned', [Tenant::class, $owner]);
        if (! $owner->tenants()->exists()) {
            return redirect()->route('owner.first-shop');
        }
        if (! $request->session()->has('additional_shop_creation_key')) {
            $request->session()->put('additional_shop_creation_key', (string) Str::uuid());
        }
        return view('central.pages.owner.tenants.first-shop', [
            'settings' => ShopSettings::fromArray()->toArray(), 'additional' => true,
        ]);
    }

    public function store(StoreOwnedTenantRequest $request, TenantCreator $creator): RedirectResponse
    {
        $creator->createOwned($request->user('central'), $request->toDto());
        $request->session()->forget('additional_shop_creation_key');
        return redirect()->route('registration.status')->with('status', 'Boutique enregistrée. Sa préparation est en cours.');
    }

    public function retry(Request $request, Tenant $tenant, TenantProvisioningRetry $retry): RedirectResponse
    {
        $retry->retry($request->user('central'), $tenant);
        return back()->with('status', 'La reprise a été demandée. Les informations de votre boutique sont conservées.');
    }
}
