<?php

namespace App\Http\Controllers\Central;

use App\DTOs\Tenant\ShopSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreTenantRequest;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Services\Central\TenantCreator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function create(User $user): View
    {
        Gate::authorize('create', [Tenant::class, $user]);

        return view('central.pages.tenants.create', [
            'user' => $user, 'shops' => $user->tenants()->with('domains')->get(),
            'creationKey' => (string) Str::uuid(), 'defaults' => ShopSettings::fromArray()->toArray(),
            'baseDomain' => config('tenancy.saas_base_domain'),
        ]);
    }

    public function store(StoreTenantRequest $request, User $user, TenantCreator $tenants): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof \App\Models\Central\User, 403);
        $tenant = $tenants->create($actor, $user, $request->toDto());

        return redirect()->route('central.users.tenants.create', $user)
            ->with('status', 'Boutique '.$tenant->shop_name.' réservée. Sa base sera préparée automatiquement par la file d’attente.');
    }
}
