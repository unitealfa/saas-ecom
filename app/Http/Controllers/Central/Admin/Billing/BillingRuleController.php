<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\UpdateBillingParametersRequest;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasInvoice;
use App\Services\Central\Billing\BillingRuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class BillingRuleController extends Controller
{
    public function __construct(private readonly BillingRuleService $rules) {}

    public function edit(?BillingRule $billingRule = null): View
    {
        $gate = Gate::forUser($this->centralActor());
        if ($billingRule === null) {
            $gate->authorize('create', BillingRule::class);
        } else {
            abort_unless($gate->any(['view', 'configure'], $billingRule), 403);
        }

        return view('central.pages.admin.billing.form', ['rule' => $billingRule, 'operationUuid' => (string) Str::uuid(),
            'editable' => $gate->allows($billingRule === null ? 'create' : 'configure', $billingRule ?? BillingRule::class)]);
    }

    public function update(UpdateBillingParametersRequest $request, ?BillingRule $billingRule = null): RedirectResponse
    {
        if ($billingRule === null) {
            $this->rules->configureInitial($this->centralActor(), $request->validated());
        } else {
            $this->rules->configure($this->centralActor(), $billingRule, $request->validated());
        }

        return (Gate::forUser($this->centralActor())->allows('viewAny', SaasInvoice::class)
            ? to_route('central.invoices.index') : to_route('dashboard'))->with('status', 'Paramètres validés et enregistrés. Les documents historiques sont conservés.');
    }
}
