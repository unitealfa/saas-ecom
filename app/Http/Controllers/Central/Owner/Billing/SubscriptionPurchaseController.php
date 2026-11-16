<?php

namespace App\Http\Controllers\Central\Owner\Billing;

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Subscriptions\PurchaseSubscriptionRequest;
use App\Http\Requests\Central\Subscriptions\StartScheduledSubscriptionRequest;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Services\Central\Plans\PublicPlanCatalog;
use App\Services\Central\Subscriptions\SubscriptionRequestService;
use App\Services\Central\Subscriptions\SubscriptionTransitionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SubscriptionPurchaseController extends Controller
{
    public function __construct(private readonly SubscriptionRequestService $requests, private readonly PublicPlanCatalog $plans) {}

    public function index(): View
    {
        Gate::forUser($this->centralActor())->authorize('purchase', Subscription::class);

        return view('central.pages.owner.billing.subscriptions.index', ['plans' => $this->plans->available(),
            'subscriptions' => Subscription::where('user_id', $this->centralActor()->id)->with(['plan', 'request.subscription', 'request.payment'])->orderByDesc('id')->get()]);
    }

    public function store(PurchaseSubscriptionRequest $request): RedirectResponse
    {
        $subscriptionRequest = $this->requests->create($this->centralActor(), $request->validated(), $request->file('proof'));

        return to_route('owner.subscription-requests.show', $subscriptionRequest)->with('status', $subscriptionRequest->getAttribute('invoice_error') ?? 'Demande enregistrée. La facture définitive et le reçu seront disponibles après confirmation du paiement intégral.');
    }

    public function startNow(StartScheduledSubscriptionRequest $request, Subscription $subscription, SubscriptionTransitionService $transitions): RedirectResponse
    {
        $transitions->startNow($this->centralActor(), $subscription, $request->validated());

        return to_route('owner.subscriptions.show', $subscription)->with('status', 'Votre nouveau plan est actif. Les anciens droits sont arrêtés et les nouveaux quotas sont appliqués.');
    }

    public function show(Subscription $subscription): View
    {
        Gate::forUser($this->centralActor())->authorize('purchase', Subscription::class);
        abort_unless($subscription->user_id === $this->centralActor()->id, 403);

        return view('central.pages.owner.billing.subscriptions.show', ['subscription' => $subscription->load(['plan.features.feature', 'request.subscription', 'request.payment']),
            'currentSubscription' => Subscription::where('user_id', $subscription->user_id)->whereKeyNot($subscription->id)
                ->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL])->currentlyEffective()->with('plan.features.feature')->orderByDesc('id')->first(),
            'installments' => SubscriptionInstallment::where('subscription_id', $subscription->id)->with('invoices')->orderBy('id')->get()]);
    }
}
