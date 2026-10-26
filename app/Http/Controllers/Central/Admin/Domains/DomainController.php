<?php

namespace App\Http\Controllers\Central\Admin\Domains;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Domains\DomainActionRequest;
use App\Http\Requests\Central\Domains\StoreDomainRequest;
use App\Models\Central\Domain;
use App\Services\Central\Domains\DomainDnsInspector;
use App\Services\Central\Domains\DomainService;
use App\Services\Central\Domains\DomainVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DomainController extends Controller
{
    public function __construct(private DomainService $domains, private DomainVerificationService $verification) {}

    public function index(Request $request, DomainDnsInspector $inspector): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', Domain::class);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'archived' => ['sometimes', 'boolean'],
        ]);

        $domains = $this->domains->search($actor, $data['search'] ?? null, $request->boolean('archived'));
        $items = $domains->getCollection();

        $primaryDomains = Domain::whereIn('tenant_id', $items->pluck('tenant_id'))
            ->where('is_primary', true)
            ->pluck('uuid', 'tenant_id');

        $verificationTokens = $items->mapWithKeys(
            fn (Domain $domain): array => [$domain->uuid => $inspector->token($domain)],
        );

        return view('central.pages.admin.domains.index', compact('domains', 'primaryDomains', 'verificationTokens'));
    }

    public function create(Request $request): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('create', Domain::class);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_uuid' => ['nullable', 'uuid'],
        ]);

        return view('central.pages.admin.domains.create', [
            'shops' => $this->domains->shops($actor, $data['search'] ?? null, $data['tenant_uuid'] ?? null),
        ]);
    }

    public function store(StoreDomainRequest $request): RedirectResponse
    {
        $this->domains->create($this->centralActor(), $request->validated('tenant_uuid'), $request->validated('domain'));

        return $this->result('Domaine ajouté. Configurez ses enregistrements DNS puis lancez la vérification.');
    }

    public function edit(Domain $domain, DomainDnsInspector $inspector): View
    {
        Gate::forUser($this->centralActor())->authorize('update', $domain);

        return view('central.pages.admin.domains.edit', [
            'domain' => $domain->load('tenant'),
            'verificationToken' => $inspector->token($domain),
        ]);
    }

    public function update(StoreDomainRequest $request, Domain $domain): RedirectResponse
    {
        $this->domains->update(
            $this->centralActor(),
            $domain,
            $request->validated('domain'),
            $request->validated('expected_state'),
        );

        return $this->result('Domaine enregistré. Une adresse modifiée doit être vérifiée à nouveau.');
    }

    public function verify(DomainActionRequest $request, Domain $domain): RedirectResponse
    {
        $result = $this->verification->verify($this->centralActor(), $domain, $request->validated('expected_state'));

        return $result['successful']
            ? $this->result($result['message'])
            : $this->destination()->withErrors(['domain' => $result['message']]);
    }

    public function primary(DomainActionRequest $request, Domain $domain): RedirectResponse
    {
        $this->domains->setPrimary(
            $this->centralActor(),
            $domain,
            $request->validated('expected_state'),
            $request->validated('expected_primary_uuid'),
        );

        return $this->result('Domaine principal enregistré.');
    }

    public function destroy(DomainActionRequest $request, Domain $domain): RedirectResponse
    {
        $this->domains->archive($this->centralActor(), $domain, $request->validated('expected_state'));

        return $this->result('Domaine archivé. Son adresse reste réservée.');
    }

    public function restore(DomainActionRequest $request, Domain $domain): RedirectResponse
    {
        $this->domains->restore($this->centralActor(), $domain, $request->validated('expected_state'));

        return $this->result('Domaine restauré en attente de vérification. La boutique et son domaine principal sont conservés.');
    }

    private function result(string $message): RedirectResponse
    {
        return $this->destination()->with('status', $message);
    }

    /** Liste des domaines si l'acteur peut la voir, sinon tableau de bord. */
    private function destination(): RedirectResponse
    {
        $canList = Gate::forUser($this->centralActor())->allows('viewAny', Domain::class);

        return redirect()->route($canList ? 'central.domains.index' : 'dashboard');
    }
}
