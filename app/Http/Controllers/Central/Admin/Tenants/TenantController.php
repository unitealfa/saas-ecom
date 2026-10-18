<?php

namespace App\Http\Controllers\Central\Admin\Tenants;

use App\DTOs\Tenant\Shop\ShopSettings;
use App\Enums\Central\Tenants\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Tenants\ChangeTenantStatusRequest;
use App\Http\Requests\Central\Tenants\SearchTenantsRequest;
use App\Http\Requests\Central\Tenants\StoreTenantRequest;
use App\Http\Requests\Central\Tenants\UpdateTenantPrioritiesRequest;
use App\Http\Requests\Central\Tenants\UpdateTenantRequest;
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use App\Models\User;
use App\Services\Central\Tenants\TenantCreator;
use App\Services\Central\Tenants\TenantManagementService;
use App\Services\Central\Tenants\TenantStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function __construct(private TenantCreator $creator, private TenantManagementService $tenants, private TenantStatusService $states) {}

    public function index(SearchTenantsRequest $request): View
    {
        $status = $request->validated('status');
        $status = $status === null ? null : StatusEnum::from((int) $status);
        $shops = $this->tenants->search($this->actor($request), $request->validated('search'), $status);

        return view('central.pages.admin.tenants.index', [
            'shops' => $shops, 'search' => $request->validated('search'), 'status' => $status, 'statuses' => StatusEnum::cases(),
            'subscriptions' => $this->tenants->subscriptions(collect($shops->items())),
        ]);
    }

    public function chooseOwner(SearchTenantsRequest $request): View
    {
        return view('central.pages.admin.tenants.create', [
            'user' => null, 'owners' => $this->tenants->owners($this->actor($request), $request->validated('search')),
            'search' => $request->validated('search'),
        ]);
    }

    public function create(User $user): View
    {
        Gate::authorize('create', [Tenant::class, $user]);

        return view('central.pages.admin.tenants.create', [
            'user' => $user, 'shops' => $user->tenants()->with('domains')->orderBy('id')->paginate(20),
            'creationKey' => (string) Str::uuid(), 'defaults' => ShopSettings::fromArray()->toArray(),
            'baseDomain' => config('tenancy.saas_base_domain'),
        ]);
    }

    public function store(StoreTenantRequest $request, User $user): RedirectResponse
    {
        $tenant = $this->creator->create($this->actor($request), $user, $request->toDto());

        return redirect()->route('central.users.tenants.create', $user)
            ->with('status', 'Boutique '.$tenant->shop_name.' réservée. Sa base sera préparée automatiquement par la file d’attente.');
    }

    public function show(Tenant $tenant): View
    {
        Gate::authorize('view', $tenant);
        $tenant->load(['owner' => fn ($query) => $query->withTrashed(), 'domains']);

        return view('central.pages.admin.tenants.show', [
            'tenant' => $tenant, 'subscription' => $this->tenants->subscriptions(collect([$tenant]))->get($tenant->user_id),
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        Gate::authorize('update', $tenant);

        return view('central.pages.admin.tenants.edit', compact('tenant'));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->tenants->update($this->actor($request), $tenant, $request->validated());

        return redirect()->route('central.tenants.show', $tenant)->with('status', 'Informations enregistrées. Le nom sera synchronisé avec la boutique par la file centrale.');
    }

    public function history(Request $request, Tenant $tenant): View
    {
        return view('central.pages.admin.tenants.history', ['tenant' => $tenant, 'history' => $this->tenants->history($this->actor($request), $tenant)]);
    }

    public function suspend(ChangeTenantStatusRequest $request, Tenant $tenant): RedirectResponse
    {
        return $this->changeState($request, $tenant, 'suspend', 'Boutique suspendue.');
    }

    public function activate(ChangeTenantStatusRequest $request, Tenant $tenant): RedirectResponse
    {
        return $this->changeState($request, $tenant, 'activate', 'Boutique activée sous son quota.');
    }

    public function archive(ChangeTenantStatusRequest $request, Tenant $tenant): RedirectResponse
    {
        return $this->changeState($request, $tenant, 'archive', 'Boutique archivée. Sa base et ses données sont conservées.');
    }

    public function priorities(UpdateTenantPrioritiesRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->states->priorities($this->actor($request), $tenant, $request->validated('priorities'), $request->validated('reason'));

        $owner = $tenant->owner;
        $response = Gate::allows('update', $owner)
            ? redirect()->route('central.users.edit', $owner)
            : redirect()->route('central.tenants.show', $tenant);

        return $response->with('status', 'Priorités enregistrées et boutiques éligibles recalculées sous quota.');
    }

    private function changeState(ChangeTenantStatusRequest $request, Tenant $tenant, string $action, string $message): RedirectResponse
    {
        $this->states->change($this->actor($request), $tenant, $action, StatusEnum::from((int) $request->validated('expected_status')), $request->validated('reason'));

        return redirect()->route('central.tenants.show', $tenant)->with('status', $message);
    }

    private function actor(Request $request): CentralUser
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof CentralUser, 403);

        return $actor;
    }
}
