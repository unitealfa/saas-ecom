<?php

namespace App\Http\Controllers\Central\Owner\Billing;

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\PaymentActionRequest;
use App\Http\Requests\Central\Billing\SearchSaasPaymentsRequest;
use App\Http\Requests\Central\Billing\StoreSaasPaymentRequest;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Services\Central\Billing\InvoicePaymentService;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Billing\SaasPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly SaasPaymentService $payments, private readonly PaymentDocumentService $documents, private readonly InvoicePaymentService $balances) {}

    public function index(SearchSaasPaymentsRequest $request): View
    {
        return view('central.pages.admin.billing.payments.index', ['payments' => $this->payments->search($this->centralActor(), $request->validated(), own: true), 'ownerMode' => true]);
    }

    public function show(SaasPayment $payment): View
    {
        Gate::forUser($this->centralActor())->authorize('viewOwn', SaasPayment::class);
        Gate::forUser($this->centralActor())->authorize('view', $payment);

        return view('central.pages.admin.billing.payments.show', ['payment' => $payment->load(['invoice.subscription', 'owner']), 'ownerMode' => true, 'history' => null]);
    }

    public function create(SaasInvoice $invoice): View
    {
        Gate::forUser($this->centralActor())->authorize('declarePayment', $invoice);

        return view('central.pages.admin.billing.payments.create', ['invoice' => $invoice, 'cash' => false, 'balance' => $this->balances->balance($this->centralActor(), $invoice)]);
    }

    public function store(StoreSaasPaymentRequest $request, SaasInvoice $invoice): RedirectResponse
    {
        $payment = $this->payments->declareExternalPayment($this->centralActor(), $invoice, $request->safe()->except('proof'), $request->file('proof'));

        return to_route('owner.payments.show', $payment)->with('status', match ($payment->getAttribute('transfer_status')) {
            TransferStatusEnum::VERIFIED => 'Ce paiement est déjà confirmé. Son reçu est disponible.',
            TransferStatusEnum::UNCERTAIN => 'Déclaration conservée pour examen administratif. Aucun droit supplémentaire accordé.',
            TransferStatusEnum::REJECTED => 'Cette déclaration a déjà été refusée. Consultez son motif.',
            default => 'Justificatif déposé. La réception des fonds doit encore être vérifiée ; aucun droit payant n’est accordé par le dépôt.',
        });
    }

    public function proof(SaasPayment $payment): StreamedResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewOwn', SaasPayment::class);

        return $this->documents->download($this->centralActor(), $payment, false);
    }

    public function resumeProof(PaymentActionRequest $request, SaasPayment $payment): RedirectResponse
    {
        $payment = $this->payments->resumeExternalProof($this->centralActor(), $payment, $request->validated('expected_state'), $request->file('proof'));

        return to_route('owner.payments.show', $payment)->with('status', 'Justificatif complété sur la même déclaration. La réception des fonds doit encore être vérifiée.');
    }

    public function receipt(SaasPayment $payment): StreamedResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewOwn', SaasPayment::class);

        return $this->documents->download($this->centralActor(), $payment, true);
    }
}
