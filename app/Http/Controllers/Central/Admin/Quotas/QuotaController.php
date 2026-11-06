<?php

namespace App\Http\Controllers\Central\Admin\Quotas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Subscriptions\ReconcileQuotaRequest;
use App\Models\Central\Users\User;
use App\Services\Central\Subscriptions\QuotaService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class QuotaController extends Controller
{
    public function __construct(private readonly QuotaService $quotas) {}

    public function index(Request $request): View
    {
        Gate::forUser($this->centralActor())->authorize('viewAnyQuota', User::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim($filters['search'] ?? '');
        $pattern = '%'.addcslashes($search, '\\%_').'%';
        $owners = User::query()->whereHas('subscriptions')->when($search !== '', fn ($q) => $q->where(
            fn ($q) => $q->where('first_name', 'like', $pattern)->orWhere('last_name', 'like', $pattern)->orWhere('email', 'like', $pattern)
        ))->orderBy('id')->paginate(20)->withQueryString();

        return view('central.pages.admin.quotas.index', compact('owners'));
    }

    public function show(User $user): View
    {
        return view('central.pages.admin.quotas.show', ['owner' => $user, ...$this->quotas->snapshot($this->centralActor(), $user)]);
    }

    public function reconcile(ReconcileQuotaRequest $request, User $user): RedirectResponse
    {
        $actor = $this->centralActor();
        $this->quotas->reconcile($actor, $user, $request->validated('expected_state'));

        return Gate::forUser($actor)->allows('viewQuota', $user)
            ? to_route('central.quotas.show', $user)->with('status', 'Quotas recalculés.')
            : to_route('dashboard')->with('status', 'Quotas recalculés.');
    }

    public function history(User $user): View
    {
        return view('central.pages.admin.quotas.history', ['owner' => $user, 'history' => $this->quotas->history($this->centralActor(), $user)]);
    }
}
