<?php

namespace App\Http\Controllers\Central\Owner\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\SearchCreditNotesRequest;
use App\Models\Central\Billing\SaasCreditNote;
use App\Services\Central\Billing\InvoicePaymentService;
use App\Services\Central\Billing\SaasCreditNoteService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CreditNoteController extends Controller
{
    public function __construct(private readonly SaasCreditNoteService $credits, private readonly SaasInvoiceIssuanceService $issuance,
        private readonly InvoicePaymentService $balances) {}

    public function index(SearchCreditNotesRequest $request): View
    {
        return view('central.pages.admin.billing.credit-notes.index', ['credits' => $this->credits->search($this->centralActor(), $request->validated(), own: true), 'ownerMode' => true]);
    }

    public function show(SaasCreditNote $credit): View
    {
        Gate::forUser($this->centralActor())->authorize('viewOwnCredit', $credit);

        return view('central.pages.admin.billing.credit-notes.show', ['credit' => $credit->load(['originalInvoice', 'lines', 'media']),
            'ownerMode' => true, 'available' => [], 'history' => null, 'balance' => $this->balances->balance($this->centralActor(), $credit->originalInvoice)]);
    }

    public function download(SaasCreditNote $credit): StreamedResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewOwnCredit', $credit);

        return $this->issuance->download($this->centralActor(), $credit);
    }
}
