<?php

namespace App\Http\Controllers\Central\Owner\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Domains\DomainActionRequest;
use App\Models\Central\Domains\Domain;
use App\Models\Central\Tenants\Tenant;
use App\Services\Central\Domains\DomainDnsInspector;
use App\Services\Central\Domains\DomainService;
use App\Services\Central\Domains\DomainVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OwnerDomainController extends Controller
{
    public function __construct(private DomainService $domains, private DomainVerificationService $verification) {}

    public function index(Tenant $tenant, DomainDnsInspector $inspector): View
    {
        Gate::forUser($this->centralActor())->authorize('createOwned', [Domain::class, $tenant]);

        return view('central.pages.owner.tenants.domains', ['tenant' => $tenant,
            'domains' => Domain::where('tenant_id', $tenant->id)->orderByDesc('is_primary')->orderBy('domain')->get(),
            'inspector' => $inspector]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        Gate::forUser($this->centralActor())->authorize('createOwned', [Domain::class, $tenant]);
        $request->merge(['domain' => is_string($request->input('domain')) ? strtolower(trim($request->input('domain'))) : $request->input('domain')]);
        $data = $request->validate(['domain' => ['required', 'string', 'max:63', 'regex:'.Tenant::SLUG_PATTERN], 'confirmed' => ['required', 'accepted']]);
        $base = trim((string) config('tenancy.saas_base_domain'), '.');
        abort_if($base === '', 503);
        $this->domains->createOwned($this->centralActor(), $tenant, $data['domain'].'.'.$base);

        return to_route('owner.shops.domains.index', $tenant)->with('status', 'Domaine ajouté. Lancez sa vérification avant de le définir comme principal.');
    }

    public function verify(DomainActionRequest $request, Tenant $tenant, Domain $domain): RedirectResponse
    {
        abort_unless($domain->tenant_id === $tenant->id, 404);
        $result = $this->verification->verify($this->centralActor(), $domain, $request->validated('expected_state'));
        $redirect = to_route('owner.shops.domains.index', $tenant);

        return $result['successful'] ? $redirect->with('status', $result['message']) : $redirect->withErrors(['domain' => $result['message']]);
    }

    public function primary(DomainActionRequest $request, Tenant $tenant, Domain $domain): RedirectResponse
    {
        abort_unless($domain->tenant_id === $tenant->id, 404);
        $this->domains->setPrimary($this->centralActor(), $domain, $request->validated('expected_state'), $request->validated('expected_primary_uuid'));

        return to_route('owner.shops.domains.index', $tenant)->with('status', 'Domaine principal enregistré.');
    }

    public function destroy(DomainActionRequest $request, Tenant $tenant, Domain $domain): RedirectResponse
    {
        abort_unless($domain->tenant_id === $tenant->id, 404);
        $this->domains->archive($this->centralActor(), $domain, $request->validated('expected_state'));

        return to_route('owner.shops.domains.index', $tenant)->with('status', 'Domaine archivé.');
    }
}
