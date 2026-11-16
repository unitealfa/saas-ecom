<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\SearchSaasInvoicesRequest;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Services\Central\Billing\InvoicePaymentService;
use App\Services\Central\Billing\OwnerPaymentCorrectionService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasInvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaasInvoiceController extends Controller
{
    public function __construct(private readonly SaasInvoiceService $invoices, private readonly InvoicePaymentService $payments, private readonly OwnerPaymentCorrectionService $corrections) {}

    public function index(SearchSaasInvoicesRequest $request): View
    {
        Gate::forUser($this->centralActor())->authorize('viewBillingDossiers', SaasInvoice::class);
        $invoices = $this->invoices->search($this->centralActor(), $request->validated());

        return view('central.pages.admin.billing.invoices.index', [
            'pendingInstallments' => Gate::forUser($this->centralActor())->allows('viewAny', SubscriptionInstallment::class) ? SubscriptionInstallment::where('installment_status', InstallmentStatusEnum::PENDING)->with(['subscription.plan', 'subscription.request', 'invoices'])->orderByDesc('id')->paginate(10, ['*'], 'due_page') : null,
            'invoices' => $invoices, 'ownerMode' => false,
            'paymentStates' => $this->payments->states($this->centralActor(), $invoices->getCollection()),
        ]);
    }

    public function show(SaasInvoice $invoice): View
    {
        Gate::forUser($this->centralActor())->authorize('viewBillingDossier', $invoice);

        return view('central.pages.admin.billing.invoices.show', [
            'correctionReports' => Gate::forUser($this->centralActor())->allows('viewAny', SaasPayment::class) ? $this->corrections->reports($this->centralActor(), $invoice) : null,
            ...$this->invoices->paymentDetails($this->centralActor(), $invoice),
            'invoice' => $invoice->load(['owner', 'installment', 'billingRule', 'sequence', 'lines', 'media', 'deliveries']),
            'ownerMode' => false, 'history' => Gate::forUser($this->centralActor())->allows('view', $invoice)
                ? Activity::forSubject($invoice)->orderByDesc('id')->paginate(20) : null,
            'paymentState' => $this->payments->states($this->centralActor(), collect([$invoice]))[$invoice->id],
            'balance' => $this->payments->balance($this->centralActor(), $invoice),
            'credits' => SaasCreditNote::where('original_invoice_id', $invoice->id)->where('status', DocumentStatusEnum::ISSUED)->get(),
        ]);
    }

    public function download(SaasInvoice $invoice, SaasInvoiceIssuanceService $issuance): StreamedResponse
    {
        Gate::forUser($this->centralActor())->authorize('downloadAdmin', $invoice);

        return $issuance->download($this->centralActor(), $invoice, inline: request()->boolean('inline'));
    }
}
