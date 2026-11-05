<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Enums\Central\Billing\PolicyStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\CreateInstallmentInvoiceRequest;
use App\Http\Requests\Central\Billing\IssueSaasInvoiceRequest;
use App\Http\Requests\Central\Billing\PrepareSaasInvoiceRequest;
use App\Http\Requests\Central\Billing\ReserveSaasInvoiceNumberRequest;
use App\Http\Requests\Central\Billing\SearchSaasInvoicesRequest;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasInvoiceService;
use App\Services\Central\Billing\SaasNumberingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SaasInvoiceController extends Controller
{
    private const int PER_PAGE = 20;

    public function __construct(private readonly SaasInvoiceService $invoices, private readonly SaasNumberingService $numbering) {}

    public function index(SearchSaasInvoicesRequest $request): View
    {
        Gate::forUser($this->centralActor())->authorize('viewAny', SaasInvoice::class);

        return view('central.pages.admin.billing.invoices.index', [
            'invoices' => $this->invoices->search($this->centralActor(), $request->validated()), 'ownerMode' => false,
        ]);
    }

    public function create(Subscription $subscription): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('createInstallment', $subscription);
        Gate::forUser($actor)->authorize('create', SaasInvoice::class);

        return view('central.pages.admin.billing.invoices.create', [
            'subscription' => $subscription->load(['owner', 'plan']),
            'rules' => BillingRule::whereIn('policy_status', [PolicyStatusEnum::ACTIVE, PolicyStatusEnum::RETIRED])
                ->orderBy('code')->orderByDesc('version')->get()->filter(fn (BillingRule $rule) => $rule->isApplicableAt(now())),
        ]);
    }

    public function store(PrepareSaasInvoiceRequest $request, Subscription $subscription): RedirectResponse
    {
        $invoice = $this->invoices->prepareCurrentPeriodWithoutTax($this->centralActor(), $subscription, $request->validated());

        return $this->redirectWithStatus($invoice, 'Échéance et intention de facture créées. Aucun document émis et aucun paiement enregistré.');
    }

    public function show(SaasInvoice $invoice): View
    {
        Gate::forUser($this->centralActor())->authorize('view', $invoice);

        return view('central.pages.admin.billing.invoices.show', [
            'invoice' => $invoice->load(['owner', 'installment', 'billingRule', 'sequence', 'lines', 'media', 'deliveries']),
            'ownerMode' => false, 'history' => Activity::forSubject($invoice)->orderByDesc('id')->paginate(20),
        ]);
    }

    public function issue(IssueSaasInvoiceRequest $request, SaasInvoice $invoice, SaasInvoiceIssuanceService $issuance): RedirectResponse
    {
        $invoice = $issuance->issue($this->centralActor(), $invoice, $request->validated('expected_state'));

        return $this->redirectWithStatus($invoice, 'Facture émise avec PDF privé. L’intention de transmission est en attente ; aucun envoi ni paiement n’est confirmé.');
    }

    public function download(SaasInvoice $invoice, SaasInvoiceIssuanceService $issuance): StreamedResponse
    {
        Gate::forUser($this->centralActor())->authorize('downloadAdmin', $invoice);

        return $issuance->download($this->centralActor(), $invoice);
    }

    public function prepareInstallment(CreateInstallmentInvoiceRequest $request, Subscription $subscription, SubscriptionInstallment $installment): RedirectResponse
    {
        $invoice = $this->invoices->prepareInstallment($this->centralActor(), $subscription, $installment, $request->validated('billing_rule_uuid'), $request->validated('expected_state'));

        return $this->redirectWithStatus($invoice, 'Facture préparée depuis l’échéance. Vérifiez-la avant émission.');
    }

    public function reserve(ReserveSaasInvoiceNumberRequest $request, SaasInvoice $invoice): RedirectResponse
    {
        $invoice = $this->numbering->reserve($this->centralActor(), $invoice, $request->validated('expected_state'));

        return $this->redirectWithStatus($invoice, 'Numéro réservé. La facture reste en préparation, sans PDF ni émission définitive.');
    }

    public function installments(Subscription $subscription): View
    {
        Gate::forUser($this->centralActor())->authorize('viewAny', SubscriptionInstallment::class);

        return view('central.pages.admin.billing.invoices.installments', [
            'subscription' => $subscription,
            'rules' => BillingRule::whereIn('policy_status', [PolicyStatusEnum::ACTIVE, PolicyStatusEnum::RETIRED])->orderBy('code')->get()
                ->filter(fn (BillingRule $rule) => $rule->isApplicableAt(now())),
            'installments' => SubscriptionInstallment::where('parent_subscription_id', $subscription->id)->with('invoices')
                ->orderByDesc('id')->paginate(self::PER_PAGE),
        ]);
    }

    private function redirectWithStatus(SaasInvoice $invoice, string $message): RedirectResponse
    {
        $redirect = Gate::forUser($this->centralActor())->allows('view', $invoice)
            ? to_route('central.invoices.show', $invoice) : to_route('dashboard');

        return $redirect->with('status', $message);
    }
}
