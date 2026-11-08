<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\PaymentActionRequest;
use App\Http\Requests\Central\Billing\SearchSaasPaymentsRequest;
use App\Http\Requests\Central\Billing\StoreSaasPaymentRequest;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Services\Central\Billing\InvoicePaymentService;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Billing\SaasPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaasPaymentController extends Controller
{
    public function __construct(private readonly SaasPaymentService $payments, private readonly PaymentDocumentService $documents, private readonly InvoicePaymentService $balances) {}

    public function index(SearchSaasPaymentsRequest $request): View
    {
        return view('central.pages.admin.billing.payments.index', ['payments' => $this->payments->search($this->centralActor(), $request->validated()), 'ownerMode' => false]);
    }

    public function show(SaasPayment $payment): View
    {
        Gate::forUser($this->centralActor())->authorize('viewAny', SaasPayment::class);
        Gate::forUser($this->centralActor())->authorize('view', $payment);

        return view('central.pages.admin.billing.payments.show', ['payment' => $payment->load(['invoice.subscription', 'owner', 'validator', 'creator']), 'ownerMode' => false,
            'history' => Activity::forSubject($payment)->orderByDesc('id')->paginate(20)]);
    }

    public function createCash(SaasInvoice $invoice): View|RedirectResponse
    {
        Gate::forUser($this->centralActor())->authorize('recordCashPayment', $invoice);
        if ($invoice->subscription->request !== null && Gate::forUser($this->centralActor())->allows('viewAny', SubscriptionRequest::class)) {
            return to_route('central.subscription-requests.show', $invoice->subscription->request);
        }

        return view('central.pages.admin.billing.payments.create', ['invoice' => $invoice, 'cash' => true, 'balance' => $this->balances->balance($this->centralActor(), $invoice)]);
    }

    public function cash(StoreSaasPaymentRequest $request, SaasInvoice $invoice): RedirectResponse
    {
        $payment = $this->payments->recordCashPayment($this->centralActor(), $invoice, $request->validated());

        return $this->redirectWithStatus($payment, $payment->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED
            ? 'Encaissement intégral confirmé. Le reçu est disponible ; les dates de la période sont respectées.'
            : 'Espèces reçues enregistrées comme anomalie. Aucun abonnement supplémentaire activé. Un rapprochement administratif est nécessaire.');
    }

    public function verify(PaymentActionRequest $request, SaasPayment $payment): RedirectResponse
    {
        $payment = $this->payments->verifyPayment($this->centralActor(), $payment, $request->validated());

        return $this->redirectWithStatus($payment, $payment->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED
            ? 'Réception intégrale confirmée. La période est payée ; ses dates de début et de fin sont conservées.'
            : 'Réception signalée conservée comme anomalie. Aucun droit supplémentaire accordé ; un rapprochement administratif est nécessaire.');
    }

    public function resumeCash(PaymentActionRequest $request, SaasPayment $payment): RedirectResponse
    {
        $payment = $this->payments->resumeCashPayment($this->centralActor(), $payment, $request->validated('expected_state'));

        return $this->redirectWithStatus($payment, $payment->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED
            ? 'Encaissement repris et intégralement confirmé. Le reçu est disponible.' : 'Constat espèces complété. L’anomalie est conservée sans activation supplémentaire.');
    }

    public function reject(PaymentActionRequest $request, SaasPayment $payment): RedirectResponse
    {
        $payment = $this->payments->rejectPayment($this->centralActor(), $payment, $request->validated('expected_state'), $request->validated('reason'));

        return $this->redirectWithStatus($payment, 'Déclaration refusée. Son motif et son historique sont conservés.');
    }

    public function reconcile(PaymentActionRequest $request, SaasPayment $payment): RedirectResponse
    {
        $payment = $this->payments->reconcilePayment($this->centralActor(), $payment, $request->validated());

        return $this->redirectWithStatus($payment, 'Rapprochement enregistré. Aucun droit supplémentaire et aucune opération bancaire ne sont déclenchés.');
    }

    public function proof(SaasPayment $payment): StreamedResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewAny', SaasPayment::class);

        return $this->documents->download($this->centralActor(), $payment, false);
    }

    public function receipt(SaasPayment $payment): StreamedResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewAny', SaasPayment::class);

        return $this->documents->download($this->centralActor(), $payment, true, inline: request()->boolean('inline'));
    }

    private function redirectWithStatus(SaasPayment $payment, string $message): RedirectResponse
    {
        $redirect = Gate::forUser($this->centralActor())->allows('viewAny', SaasPayment::class)
            ? to_route('central.payments.show', $payment) : to_route('dashboard');

        return $redirect->with('status', $message);
    }
}
