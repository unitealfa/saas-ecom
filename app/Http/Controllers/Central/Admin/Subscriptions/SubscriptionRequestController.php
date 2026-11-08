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

class SubscriptionRequestController extends Controller
{
    public function __construct(private readonly SubscriptionRequestService $requests) {}

    public function index(SearchSubscriptionRequestsRequest $request): View
    {
        return view('central.pages.admin.subscriptions.requests.index', ['requests' => $this->requests->search($this->centralActor(), $request->validated()), 'ownerMode' => false]);
    }

    public function show(SubscriptionRequest $subscriptionRequest): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', SubscriptionRequest::class);
        Gate::forUser($actor)->authorize('view', $subscriptionRequest);

        return view('central.pages.admin.subscriptions.requests.show', ['subscriptionRequest' => $subscriptionRequest->load(['owner', 'subscription.plan', 'invoice.media', 'payment']),
            'ownerMode' => false, 'history' => Gate::forUser($actor)->allows('history', $subscriptionRequest)
                ? Activity::forSubject($subscriptionRequest)->orderByDesc('id')->paginate(20) : null]);
    }

    public function accept(SubscriptionRequestActionRequest $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        $current = $this->requests->accept($this->centralActor(), $subscriptionRequest, $request->validated('expected_state'), $request->safe()->except('expected_state'));
        $message = 'Réception conservée comme anomalie ; demande non acceptée, sans droit supplémentaire.';
        if ($current->status === RequestStatusEnum::ACCEPTED) {
            $message = $current->payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->exists()
                ? 'Paiement intégral confirmé et demande acceptée. Le reçu est disponible.'
                : 'Paiement intégral confirmé et demande acceptée. La génération du reçu doit être reprise depuis son lien, sans nouvel encaissement.';
        }

        return $this->redirectWithStatus($current, $message);
    }

    public function reject(SubscriptionRequestActionRequest $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        $current = $this->requests->reject($this->centralActor(), $subscriptionRequest, $request->validated('expected_state'), $request->validated('reason'), $request->validated('expected_payment_state'));

        return $this->redirectWithStatus($current, 'Demande refusée. Le motif et les pièces précédentes sont conservés.');
    }

    public function prepareInvoice(SubscriptionRequestActionRequest $request, SubscriptionRequest $subscriptionRequest): RedirectResponse
    {
        $current = $this->requests->prepareInvoice($this->centralActor(), $subscriptionRequest, $request->validated('expected_state'));

        return $this->redirectWithStatus($current, $current->getAttribute('invoice_error') ?? 'Facture disponible, sans confirmation de paiement.');
    }

    private function redirectWithStatus(SubscriptionRequest $request, string $message): RedirectResponse
    {
        $redirect = Gate::forUser($this->centralActor())->allows('viewAny', SubscriptionRequest::class)
            ? to_route('central.subscription-requests.show', $request) : to_route('dashboard');

        return $redirect->with('status', $message);
    }
}
