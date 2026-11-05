<?php

namespace App\Http\Controllers\Central\Owner\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\SearchSaasInvoicesRequest;
use App\Models\Central\Billing\SaasInvoice;
use App\Services\Central\Billing\InvoicePaymentService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasInvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(private readonly SaasInvoiceService $invoices, private readonly InvoicePaymentService $payments) {}

    public function index(SearchSaasInvoicesRequest $request): View
    {
        $invoices = $this->invoices->search($this->centralActor(), $request->validated(), own: true);

        return view('central.pages.admin.billing.invoices.index', [
            'invoices' => $invoices, 'ownerMode' => true,
            'paymentStates' => $this->payments->states($this->centralActor(), $invoices->getCollection()),
        ]);
    }

    public function show(SaasInvoice $invoice): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewOwn', SaasInvoice::class);
        Gate::forUser($actor)->authorize('viewOwnInvoice', $invoice);

        return view('central.pages.admin.billing.invoices.show', [
            'invoice' => $invoice->load(['installment', 'sequence', 'lines', 'media']), 'ownerMode' => true, 'history' => null,
            'paymentState' => $this->payments->states($actor, collect([$invoice]))[$invoice->id],
        ]);
    }

    public function download(SaasInvoice $invoice, SaasInvoiceIssuanceService $issuance): StreamedResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewOwn', SaasInvoice::class);

        return $issuance->download($this->centralActor(), $invoice);
    }
}
