<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Enums\Central\Billing\PolicyStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\PrepareSaasInvoiceRequest;
use App\Http\Requests\Central\Billing\ReserveSaasInvoiceNumberRequest;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\SaasInvoiceService;
use App\Services\Central\Billing\SaasNumberingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SaasInvoiceController extends Controller
{
    private const int PER_PAGE = 20;

    public function __construct(private readonly SaasInvoiceService $invoices, private readonly SaasNumberingService $numbering) {}

    public function index(Request $request): View
    {
        Gate::forUser($this->centralActor())->authorize('viewAny', SaasInvoice::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim($filters['search'] ?? '');

        return view('central.pages.admin.billing.invoices.index', [
            'invoices' => SaasInvoice::with('owner')->when($search !== '', fn (Builder $query) => $query->where('number', 'like', '%'.addcslashes($search, '\\%_').'%'))
                ->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString(),
        ]);
    }

    public function create(Subscription $subscription): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('createInstallment', $subscription);
        Gate::forUser($actor)->authorize('create', SaasInvoice::class);

        return view('central.pages.admin.billing.invoices.create', [
            'subscription' => $subscription->load(['owner', 'plan']),
            'rules' => BillingRule::where('policy_status', PolicyStatusEnum::ACTIVE)
                ->orderBy('code')->orderByDesc('version')->get()->filter(fn (BillingRule $rule): bool => $rule->isActiveAt(now())
                    && Validator::make($rule->only(['code', 'trigger_event', 'parameters']), BillingRuleService::contentRules())->passes()),
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

        return view('central.pages.admin.billing.invoices.show', ['invoice' => $invoice->load(['owner', 'installment', 'billingRule', 'sequence', 'lines'])]);
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
