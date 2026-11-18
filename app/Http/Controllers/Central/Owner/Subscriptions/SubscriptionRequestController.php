<?php

namespace App\Http\Controllers\Central\Owner\Subscriptions;

use App\Enums\Central\Subscriptions\PaymentChoiceEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Subscriptions\SearchSubscriptionRequestsRequest;
use App\Http\Requests\Central\Subscriptions\SubscriptionRequestActionRequest;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Plans\PublicPlanCatalog;
use App\Services\Central\Subscriptions\SubscriptionRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionRequestController extends Controller
{
    public function __construct(private readonly SubscriptionRequestService $requests, private readonly PublicPlanCatalog $plans) {}

    public function index(SearchSubscriptionRequestsRequest $request): View
    {
        return view('central.pages.admin.subscriptions.requests.index', ['requests' => $this->requests->search($this->centralActor(), $request->validated(), own: true), 'ownerMode' => true]);
    }

    public function show(SubscriptionRequest $subscriptionRequest): View
    {
        $actor = $this->centralActor();
        abort_unless($actor->id === $subscriptionRequest->user_id, 403);
        Gate::forUser($actor)->authorize('view', $subscriptionRequest);

        return view('central.pages.admin.subscriptions.requests.show', ['subscriptionRequest' => $subscriptionRequest->load(['owner', 'subscription.plan.features.feature', 'invoice.media', 'payment', 'decider']),
            'currentSubscription' => $this->requests->currentSubscription($actor, $subscriptionRequest)?->load('plan.features.feature'),
            'ownerMode' => true, 'plans' => $this->plans->available(), 'history' => Activity::forSubject($subscriptionRequest)->with('causer')->orderByDesc('id')->paginate(20)]);
    }

    public function revise(SubscriptionRequestActionRequest $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        $current = $this->requests->revise($this->centralActor(), $subscriptionRequest, $request->validated('expected_state'), $request->safe()->except('expected_state'));

        return to_route('owner.subscription-requests.show', $current)->with('status', 'Offre ou période modifiée. Aucun paiement ni droit supplémentaire confirmé.');
    }

    public function cancel(SubscriptionRequestActionRequest $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        $this->requests->cancel($this->centralActor(), $subscriptionRequest, $request->validated('expected_state'));

        return to_route('owner.subscriptions.index')->with('status', 'Demande annulée. Vous pouvez choisir une autre offre. L’historique et les justificatifs sont conservés.');
    }

    public function choice(SubscriptionRequestActionRequest $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        $this->requests->changePaymentChoice($this->centralActor(), $subscriptionRequest, $request->validated('expected_state'), PaymentChoiceEnum::from((int) $request->validated('payment_choice')));

        return to_route('owner.subscription-requests.show', $subscriptionRequest)->with('status', 'Choix mis à jour, sans confirmation de paiement.');
    }

    public function proof(SubscriptionRequestActionRequest $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        $this->requests->submitProof($this->centralActor(), $subscriptionRequest, $request->validated('expected_state'), $request->safe()->except(['proof', 'expected_state']), $request->file('proof'));

        return to_route('owner.subscription-requests.show', $subscriptionRequest)->with('status', 'Justificatif transmis. La réception réelle des fonds doit encore être confirmée.');
    }

    public function initialProof(SubscriptionRequest $subscriptionRequest, PaymentDocumentService $documents): StreamedResponse
    {
        return $documents->downloadRequestProof($this->centralActor(), $subscriptionRequest, inline: request()->boolean('inline'));
    }
}
