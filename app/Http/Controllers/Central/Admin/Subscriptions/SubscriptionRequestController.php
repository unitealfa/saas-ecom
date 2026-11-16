<?php

namespace App\Http\Controllers\Central\Admin\Subscriptions;

use App\Enums\Central\Subscriptions\RequestStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Subscriptions\SearchSubscriptionRequestsRequest;
use App\Http\Requests\Central\Subscriptions\SubscriptionRequestActionRequest;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Subscriptions\SubscriptionRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionRequestController extends Controller
{
    public function __construct(private readonly SubscriptionRequestService $requests) {}

    public function index(SearchSubscriptionRequestsRequest $request): View
    {
        return view('central.pages.admin.subscriptions.index', ['requests' => $this->requests->search($this->centralActor(), $request->validated()), 'ownerMode' => false]);
    }

    public function show(SubscriptionRequest $subscriptionRequest): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', SubscriptionRequest::class);
        Gate::forUser($actor)->authorize('view', $subscriptionRequest);

        return view('central.pages.admin.subscriptions.requests.show', ['subscriptionRequest' => $subscriptionRequest->load(['owner', 'subscription.plan', 'invoice.media', 'payment', 'decider']),
            'currentSubscription' => $this->requests->currentSubscription($actor, $subscriptionRequest),
            'ownerMode' => false, 'history' => Gate::forUser($actor)->allows('history', $subscriptionRequest)
                ? Activity::forSubject($subscriptionRequest)->with('causer')->orderByDesc('id')->paginate(20) : null]);
    }

    public function accept(SubscriptionRequestActionRequest $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        $current = $this->requests->accept($this->centralActor(), $subscriptionRequest, $request->validated('expected_state'), $request->safe()->except('expected_state'));
        $message = 'Réception conservée comme anomalie ; demande non acceptée, sans droit supplémentaire.';
        if ($current->status === RequestStatusEnum::ACCEPTED) {
            $message = $current->payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->exists()
                ? 'Demande d’abonnement acceptée. Le paiement intégral est confirmé et le reçu est disponible.'
                : 'Demande d’abonnement acceptée. Le paiement intégral est confirmé ; la génération du reçu doit être reprise sans nouvel encaissement.';
        }

        return $this->redirectWithStatus($current, $message);
    }

    public function reject(SubscriptionRequestActionRequest $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        $current = $this->requests->reject($this->centralActor(), $subscriptionRequest, $request->validated('expected_state'), $request->validated('reason'), $request->validated('expected_payment_state'));

        return $this->redirectWithStatus($current, 'Demande d’abonnement refusée. Le motif est enregistré et visible par le commerçant. Les pièces précédentes sont conservées.');
    }

    public function initialProof(SubscriptionRequest $subscriptionRequest, PaymentDocumentService $documents): StreamedResponse
    {
        return $documents->downloadRequestProof($this->centralActor(), $subscriptionRequest);
    }

    private function redirectWithStatus(SubscriptionRequest $request, string $message): RedirectResponse
    {
        $redirect = Gate::forUser($this->centralActor())->allows('viewAny', SubscriptionRequest::class)
            ? to_route('central.subscription-requests.show', $request) : to_route('dashboard');

        return $redirect->with('status', $message)
            ->with('status_variant', in_array($request->status, [RequestStatusEnum::ACCEPTED, RequestStatusEnum::REJECTED], true) ? 'success' : 'warning');
    }
}
