<?php

namespace App\Http\Controllers\Central\Owner\Billing;

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Subscriptions\PurchaseSubscriptionRequest;
use App\Http\Requests\Central\Subscriptions\StartScheduledSubscriptionRequest;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Services\Central\Plans\PublicPlanCatalog;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Central\Subscriptions\SubscriptionRequestService;
use App\Services\Central\Subscriptions\SubscriptionTransitionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SubscriptionPurchaseController extends Controller
{
    public function __construct(private readonly SubscriptionRequestService $requests, private readonly PublicPlanCatalog $plans) {}

    public function index(): View
    {
        Gate::forUser($this->centralActor())->authorize('purchase', Subscription::class);

        $owner = $this->centralActor();

        return view('central.pages.owner.billing.subscriptions.index', ['plans' => $this->plans->available(),
            'subscriptions' => Subscription::where('user_id', $owner->id)->with(['plan', 'request.subscription', 'request.payment'])->orderByDesc('id')->get(),
            'currentSubscription' => Subscription::where('user_id', $owner->id)
                ->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL])->currentlyEffective()
                ->with('plan.features.feature')->orderByDesc('id')->first(),
            'defaultPlan' => Plan::where('is_default', true)->active()->with('features.feature')->first(),
        ]);
    }

    public function store(PurchaseSubscriptionRequest $request): RedirectResponse
    {
        $subscriptionRequest = $this->requests->create($this->centralActor(), $request->validated(), $request->file('proof'));

        $message = $subscriptionRequest->subscription->agreed_amount === '0.00'
            ? 'Demande enregistrée. Votre facture à 0,00 DZD sera disponible après validation.'
            : 'Demande enregistrée. La facture définitive et le reçu seront disponibles après confirmation du paiement intégral.';

        return to_route('owner.subscription-requests.show', $subscriptionRequest)->with('status', $subscriptionRequest->getAttribute('invoice_error') ?? $message);
    }

    public function switchToDefault(Request $request, Subscription $subscription, DefaultSubscription $defaults): RedirectResponse
    {
        $owner = $this->centralActor();
        Gate::forUser($owner)->authorize('purchase', Subscription::class);
        abort_unless($subscription->user_id === $owner->id, 403);
        $data = $request->validate([
            'expected_state' => ['required', 'string', 'size:64'],
            'plan_uuid' => ['required', 'uuid'],
            'expected_plan_state' => ['required', 'string', 'size:64'],
            'confirmed' => ['required', 'accepted'],
        ]);
        $next = $defaults->switchToDefault($owner, $subscription, $data['expected_state'], $data['plan_uuid'], $data['expected_plan_state']);

        return to_route('owner.subscriptions.show', $next)->with('status', 'Le plan par défaut est actif. Les nouveaux avantages et quotas sont appliqués.');
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
