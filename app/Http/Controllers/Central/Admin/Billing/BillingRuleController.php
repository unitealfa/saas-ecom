<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\BillingRuleActionRequest;
use App\Http\Requests\Central\Billing\InitializeBillingSequenceRequest;
use App\Http\Requests\Central\Billing\SearchBillingRulesRequest;
use App\Http\Requests\Central\Billing\StoreBillingRuleRequest;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\BillingSequence;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\SaasNumberingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class BillingRuleController extends Controller
{
    public function __construct(private readonly BillingRuleService $rules) {}

    public function index(SearchBillingRulesRequest $request): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', BillingRule::class);
        $filters = $request->validated();

        return view('central.pages.admin.billing.index', ['rules' => $this->rules->search($actor, $filters)]);
    }

    public function sequences(): View
    {
        $actor = $this->centralActor();

        return view('central.pages.admin.billing.sequences', [
            'sequences' => $this->rules->sequences($actor),
            'initializableTypes' => array_filter(DocumentTypeEnum::cases(), fn (DocumentTypeEnum $type): bool => Gate::forUser($actor)->allows('initialize', [BillingSequence::class, $type])),
        ]);
    }

    public function create(): View
    {
        Gate::forUser($this->centralActor())->authorize('create', BillingRule::class);

        return $this->form();
    }

    public function createVersion(BillingRule $billingRule): View
    {
        Gate::forUser($this->centralActor())->authorize('createVersion', $billingRule);

        return $this->form($billingRule, true);
    }

    public function store(StoreBillingRuleRequest $request): RedirectResponse
    {
        $rule = $this->rules->create($this->centralActor(), $request->ruleData(), $request->validated('operation_uuid'));

        return $this->redirectWithStatus($rule, 'Règle créée en brouillon.');
    }

    public function storeVersion(StoreBillingRuleRequest $request, BillingRule $billingRule): RedirectResponse
    {
        $rule = $this->rules->createVersion(
            $this->centralActor(), $billingRule, $request->ruleData(),
            $request->validated('expected_state'), $request->validated('operation_uuid'),
        );

        return $this->redirectWithStatus($rule, 'Nouvelle version créée en brouillon. Les anciennes versions sont conservées.');
    }

    public function show(BillingRule $billingRule): View
    {
        Gate::forUser($this->centralActor())->authorize('view', $billingRule);

        return view('central.pages.admin.billing.show', [
            'rule' => $billingRule->load(['creator', 'validator'])->loadExists('documents'),
            'versions' => $this->rules->versions($this->centralActor(), $billingRule),
        ]);
    }

    public function edit(BillingRule $billingRule): View
    {
        Gate::forUser($this->centralActor())->authorize('update', $billingRule);

        return $this->form($billingRule);
    }

    public function update(StoreBillingRuleRequest $request, BillingRule $billingRule): RedirectResponse
    {
        $this->rules->update($this->centralActor(), $billingRule, $request->ruleData(), $request->validated('expected_state'));

        return $this->redirectWithStatus($billingRule, 'Brouillon enregistré.');
    }

    public function activate(BillingRuleActionRequest $request, BillingRule $billingRule): RedirectResponse
    {
        $this->rules->activate(
            $this->centralActor(), $billingRule, $request->validated('expected_state'),
            $request->validated('effective_at'), $request->validated('ends_at'),
        );

        return $this->redirectWithStatus($billingRule, 'Version activée sur la période indiquée.');
    }

    public function validateRule(BillingRuleActionRequest $request, BillingRule $billingRule): RedirectResponse
    {
        $this->rules->validateRule($this->centralActor(), $billingRule, $request->validated('expected_state'), $request->validated('validation_reference'));

        return $this->redirectWithStatus($billingRule, 'Version validée. Son contenu est figé ; elle peut maintenant être activée.');
    }

    public function initializeSequence(InitializeBillingSequenceRequest $request, SaasNumberingService $numbering): RedirectResponse
    {
        $numbering->initialize($this->centralActor(), DocumentTypeEnum::from((int) $request->validated('document_type')), (int) $request->validated('fiscal_year'));
        $canView = Gate::forUser($this->centralActor())->allows('viewAny', BillingSequence::class);

        return ($canView ? to_route('central.billing-sequences.index') : to_route('dashboard'))
            ->with('status', 'Compteur initialisé ou retrouvé, sans consommation de numéro.');
    }

    public function retire(BillingRuleActionRequest $request, BillingRule $billingRule): RedirectResponse
    {
        $this->rules->retire(
            $this->centralActor(), $billingRule, $request->validated('expected_state'),
            $request->validated('reason'), $request->validated('ends_at'),
        );

        return $this->redirectWithStatus($billingRule, 'Retrait enregistré. Les documents existants sont conservés ; cette version ne peut plus servir à une nouvelle facturation.');
    }

    private function form(?BillingRule $rule = null, bool $newVersion = false): View
    {
        return view('central.pages.admin.billing.form', [
            'rule' => $rule, 'newVersion' => $newVersion, 'operationUuid' => (string) Str::uuid(),
        ]);
    }

    private function redirectWithStatus(BillingRule $rule, string $message): RedirectResponse
    {
        $redirect = Gate::forUser($this->centralActor())->allows('view', $rule)
            ? to_route('central.billing-rules.show', $rule)
            : to_route('dashboard');

        return $redirect->with('status', $message);
    }
}
