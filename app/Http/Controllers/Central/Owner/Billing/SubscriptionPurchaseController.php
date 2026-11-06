<?php

namespace App\Http\Controllers\Central\Owner\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Subscriptions\PurchaseSubscriptionRequest;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Services\Central\Plans\PublicPlanCatalog;
use App\Services\Central\Subscriptions\SubscriptionPurchaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SubscriptionPurchaseController extends Controller
{
    public function __construct(private readonly SubscriptionPurchaseService $purchases, private readonly PublicPlanCatalog $plans) {}

    public function index(): View
    {
        Gate::forUser($this->centralActor())->authorize('purchase', Subscription::class);

        return view('central.pages.owner.billing.subscriptions.index', ['plans' => $this->plans->available(),
            'subscriptions' => Subscription::where('user_id', $this->centralActor()->id)->with('plan')->orderByDesc('id')->get()]);
    }

    public function store(PurchaseSubscriptionRequest $request): RedirectResponse
    {
        $subscription = $this->purchases->choose($this->centralActor(), $request->validated());

        return to_route('owner.subscriptions.show', $subscription)->with('status', 'Offre choisie. La période sera activée après émission de la facture et confirmation intégrale du paiement.');
    }

    public function show(Subscription $subscription): View
    {
        Gate::forUser($this->centralActor())->authorize('purchase', Subscription::class);
        abort_unless($subscription->user_id === $this->centralActor()->id, 403);

        return view('central.pages.owner.billing.subscriptions.show', ['subscription' => $subscription->load('plan'),
            'installments' => SubscriptionInstallment::where('parent_subscription_id', $subscription->id)->with('invoices')->orderBy('id')->get()]);
    }
}
