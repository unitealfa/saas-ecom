<?php

namespace App\Http\Controllers\Central\Admin\Subscriptions;

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Subscriptions\SearchSubscriptionsRequest;
use App\Http\Requests\Central\Subscriptions\AssignSubscriptionRequest;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Subscriptions\Subscription;
use App\Services\Central\Subscriptions\SubscriptionAssignmentService;
use App\Services\Central\Subscriptions\SubscriptionCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionCatalogService $subscriptions,
        private readonly SubscriptionAssignmentService $assignments,
    ) {}

    public function index(SearchSubscriptionsRequest $request): View
    {
        $status = $request->validated('status');

        return view('central.pages.admin.subscriptions.index', [
            'subscriptions' => $this->subscriptions->paginate($this->centralActor(), $request->validated('search'), $status === null ? null : (int) $status),
            'statuses' => StatusEnum::cases(),
        ]);
    }

    public function show(Subscription $subscription): View
    {
        return view('central.pages.admin.subscriptions.show', [
            'subscription' => $this->subscriptions->details($this->centralActor(), $subscription),
        ]);
    }

    public function create(): View
    {
        Gate::forUser($this->centralActor())->authorize('assign', Subscription::class);

        return view('central.pages.admin.subscriptions.create', [
            'plans' => Plan::active()->where('code', '!=', 'free')->orderBy('name')->orderByDesc('version')->get(),
            'operationKey' => (string) Str::uuid(),
        ]);
    }

    public function store(AssignSubscriptionRequest $request): RedirectResponse
    {
        $subscription = $this->assignments->assign($this->centralActor(), $request->validated());
        $redirect = Gate::forUser($this->centralActor())->allows('view', $subscription)
            ? to_route('central.subscriptions.show', $subscription)
            : to_route('dashboard');

        return $redirect->with('status', 'Abonnement attribué.');
    }
}
