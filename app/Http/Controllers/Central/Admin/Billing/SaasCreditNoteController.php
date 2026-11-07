<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Enums\Central\Billing\PolicyStatusEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\CreditNoteActionRequest;
use App\Http\Requests\Central\Billing\SearchCreditNotesRequest;
use App\Http\Requests\Central\Billing\StoreSaasCreditNoteRequest;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasInvoice;
use App\Services\Central\Billing\InvoicePaymentService;
use App\Services\Central\Billing\SaasCreditNoteService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasNumberingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaasCreditNoteController extends Controller
{
    public function __construct(private readonly SaasCreditNoteService $credits, private readonly SaasInvoiceIssuanceService $issuance,
        private readonly SaasNumberingService $numbering, private readonly InvoicePaymentService $balances) {}

    public function index(SearchCreditNotesRequest $request): View
    {
        return view('central.pages.admin.billing.credit-notes.index', ['credits' => $this->credits->search($this->centralActor(), $request->validated()), 'ownerMode' => false]);
    }

    public function create(SaasInvoice $invoice): View
    {
        Gate::forUser($this->centralActor())->authorize('create', SaasCreditNote::class);

        return $this->form($invoice, null);
    }

    public function store(StoreSaasCreditNoteRequest $request, SaasInvoice $invoice): RedirectResponse
    {
        $credit = $this->credits->createCreditNoteDraft($this->centralActor(), $invoice, $request->validated());

        return $this->redirectWithStatus($credit, 'Brouillon créé. Les corrections sont réservées ; aucun avoir n’est encore émis.');
    }

    public function edit(SaasCreditNote $credit): View
    {
        Gate::forUser($this->centralActor())->authorize('update', $credit);

        return $this->form($credit->originalInvoice, $credit->load('lines'));
    }

    public function update(StoreSaasCreditNoteRequest $request, SaasCreditNote $credit): RedirectResponse
    {
        $credit = $this->credits->updateDraft($this->centralActor(), $credit, $request->validated());

        return $this->redirectWithStatus($credit, 'Brouillon modifié et réservations recalculées.');
    }

    public function show(SaasCreditNote $credit): View
    {
        Gate::forUser($this->centralActor())->authorize('view', $credit);

        return view('central.pages.admin.billing.credit-notes.show', ['credit' => $credit->load(['originalInvoice', 'lines', 'media']), 'ownerMode' => false,
            'balance' => $this->balances->balance($this->centralActor(), $credit->originalInvoice),
            'available' => $this->credits->availableLines($this->centralActor(), $credit->originalInvoice),
            'history' => Activity::forSubject($credit)->latest('id')->paginate(20)]);
    }

    public function prepare(CreditNoteActionRequest $request, SaasCreditNote $credit): RedirectResponse
    {
        $this->numbering->reserveNextNumber($this->centralActor(), $credit, $request->validated('expected_state'), $this->credits->assertIssuable(...));

        return $this->redirectWithStatus($credit->refresh(), 'Numéro d’avoir réservé. Le contenu est figé, sans émission ni remboursement.');
    }

    public function issue(CreditNoteActionRequest $request, SaasCreditNote $credit): RedirectResponse
    {
        $this->issuance->issue($this->centralActor(), $credit, $request->validated('expected_state'));

        return $this->redirectWithStatus($credit->refresh(), 'Avoir émis avec PDF privé. Le solde est recalculé, sans remboursement automatique.');
    }

    public function cancel(CreditNoteActionRequest $request, SaasCreditNote $credit): RedirectResponse
    {
        $this->credits->cancelDraft($this->centralActor(), $credit, $request->validated('expected_state'), $request->validated('reason'));

        return $this->redirectWithStatus($credit->refresh(), 'Brouillon annulé et réserve libérée. Son historique et son éventuel numéro restent conservés.');
    }

    public function download(SaasCreditNote $credit): StreamedResponse
    {
        Gate::forUser($this->centralActor())->authorize('view', $credit);

        return $this->issuance->download($this->centralActor(), $credit);
    }

    private function form(SaasInvoice $invoice, ?SaasCreditNote $credit): View
    {
        return view('central.pages.admin.billing.credit-notes.create', ['invoice' => $invoice, 'credit' => $credit,
            'available' => $this->credits->availableLines($this->centralActor(), $invoice, $credit),
            'rules' => BillingRule::where('trigger_event', TriggerEventEnum::INVOICE_CORRECTION)->where('policy_status', PolicyStatusEnum::ACTIVE)
                ->orderBy('code')->get()->filter(fn (BillingRule $rule): bool => $rule->isActiveAt(now()))]);
    }

    private function redirectWithStatus(SaasCreditNote $credit, string $message): RedirectResponse
    {
        return (Gate::forUser($this->centralActor())->allows('view', $credit) ? to_route('central.credit-notes.show', $credit) : to_route('dashboard'))
            ->with('status', $message);
    }
}
