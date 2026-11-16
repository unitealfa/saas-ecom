<?php

namespace App\Http\Controllers\Central\Owner\Billing;

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\PaymentActionRequest;
use App\Http\Requests\Central\Billing\SearchSaasPaymentsRequest;
use App\Http\Requests\Central\Billing\StoreSaasPaymentRequest;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Billing\SaasPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly SaasPaymentService $payments, private readonly PaymentDocumentService $documents) {}

    public function index(SearchSaasPaymentsRequest $request): RedirectResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewOwn', SaasPayment::class);

        return to_route('owner.billing.index');
    }

    public function show(SaasPayment $payment): RedirectResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewOwn', SaasPayment::class);
        Gate::forUser($this->centralActor())->authorize('view', $payment);

        return to_route('owner.invoices.show', $payment->invoice)->withFragment('payment-'.$payment->uuid);
    }

    public function create(SaasInvoice $invoice): RedirectResponse
    {
        Gate::forUser($this->centralActor())->authorize('declarePayment', $invoice);

        return to_route('owner.invoices.show', $invoice);
    }

    public function store(StoreSaasPaymentRequest $request, SaasInvoice $invoice): RedirectResponse
    {
        $payment = $this->payments->declareExternalPayment($this->centralActor(), $invoice, $request->safe()->except('proof'), $request->file('proof'));

        return to_route('owner.invoices.show', $payment->invoice)->withFragment('payment-'.$payment->uuid)->with('status', match ($payment->getAttribute('transfer_status')) {
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

        return to_route('owner.invoices.show', $payment->invoice)->withFragment('payment-'.$payment->uuid)->with('status', 'Justificatif complété sur la même déclaration. La réception des fonds doit encore être vérifiée.');
    }

    public function receipt(SaasPayment $payment): StreamedResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewOwn', SaasPayment::class);

        return $this->documents->download($this->centralActor(), $payment, true, inline: request()->boolean('inline'));
    }
}
