<?php

namespace App\Policies\Central;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;
use Illuminate\Support\Facades\Gate;

class SaasInvoicePolicy
{
    public function issueForRequest(User $actor, SaasInvoice $invoice): bool
    {
        $request = $invoice->subscription->request;

        return $request !== null && $request->current_invoice_id === $invoice->id
            && $request->current_subscription_id === $invoice->subscription_id
            && ($invoice->total_amount === '0.00' && $invoice->subscription->agreed_amount === '0.00'
                && $request->current_payment_id === null
                ? Gate::forUser($actor)->allows('accept', $request)
                : $request->payment?->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED
                    && Gate::forUser($actor)->allows('verify', $request->payment));
    }

    public function reserveForRequest(User $actor, SaasInvoice $invoice): bool
    {
        return in_array($invoice->status, [DocumentStatusEnum::DRAFT, DocumentStatusEnum::PREPARING], true) && $this->issueForRequest($actor, $invoice);
    }

    public function synchronizeBalance(User $actor, SaasInvoice $invoice): bool
    {
        return ! tenancy()->initialized && ($this->access->allows($actor, 'saas.credit-notes.issue')
            || $this->access->allows($actor, 'saas.payments.validate'));
    }

    public function viewFinancialBalance(User $actor, SaasInvoice $invoice): bool
    {
        return ! tenancy()->initialized && ($this->owns($actor, $invoice) || $this->viewAny($actor)
            || $this->access->allows($actor, 'saas.payments.view')
            || $this->access->allows($actor, 'saas.payments.validate')
            || $this->access->allows($actor, 'saas.credit-notes.view')
            || $this->access->allows($actor, 'saas.credit-notes.create')
            || $this->access->allows($actor, 'saas.credit-notes.issue'));
    }

    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
    }

    public function viewBillingDossiers(User $actor): bool
    {
        return $this->viewAny($actor) || (! tenancy()->initialized && $this->access->allows($actor, 'saas.payments.view'));
    }

    public function viewBillingDossier(User $actor, SaasInvoice $invoice): bool
    {
        return $this->viewBillingDossiers($actor);
    }

    public function declarePayment(User $actor, SaasInvoice $invoice): bool
    {
        $request = $invoice->subscription->request;

        return $this->payable($invoice) && (($this->owns($actor, $invoice) && $actor->hasVerifiedEmail())
            || ($request !== null && ! tenancy()->initialized && $actor->id !== $invoice->user_id && $this->access->allows($actor, 'saas.payments.validate')));
    }

    public function recordCashPayment(User $actor, SaasInvoice $invoice): bool
    {
        return ! tenancy()->initialized && $actor->id !== $invoice->user_id && $this->payable($invoice)
            && $this->access->allows($actor, 'saas.payments.validate');
    }

    public function view(User $actor, SaasInvoice $invoice): bool
    {
        return $this->viewAny($actor);
    }

    public function viewOwnInvoice(User $actor, SaasInvoice $invoice): bool
    {
        return $this->owns($actor, $invoice);
    }

    public function downloadAdmin(User $actor, SaasInvoice $invoice): bool
    {
        return $invoice->status === DocumentStatusEnum::ISSUED && $this->has($actor, 'download');
    }

    public function viewOwn(User $actor): bool
    {
        return ! tenancy()->initialized && ! $this->access->isStaff($actor);
    }

    public function issue(User $actor, SaasInvoice $invoice): bool
    {
        return $this->has($actor, 'issue')
            && ($invoice->total_amount !== '0.00' || $invoice->subscription->request === null
                || $invoice->status === DocumentStatusEnum::ISSUED)
            && in_array($invoice->status, [DocumentStatusEnum::DRAFT, DocumentStatusEnum::PREPARING, DocumentStatusEnum::ISSUED], true);
    }

    public function download(User $actor, SaasInvoice $invoice): bool
    {
        return $invoice->status === DocumentStatusEnum::ISSUED
            && ($this->has($actor, 'download') || $this->owns($actor, $invoice));
    }

    private function owns(User $actor, SaasInvoice $invoice): bool
    {
        return $this->viewOwn($actor) && $actor->id === $invoice->user_id;
    }

    private function payable(SaasInvoice $invoice): bool
    {
        if ($invoice->total_amount === '0.00') {
            return false;
        }

        $request = $invoice->subscription->request;

        return $invoice->status === DocumentStatusEnum::ISSUED
            || ($invoice->status === DocumentStatusEnum::DRAFT && $request !== null && ! $request->status->isFinal()
                && $invoice->subscription->status === StatusEnum::SCHEDULED
                && $request->current_invoice_id === $invoice->id && $request->current_subscription_id === $invoice->subscription_id);
    }

    public function create(User $actor): bool
    {
        return $this->has($actor, 'create');
    }

    public function reserveNumber(User $actor, SaasInvoice $invoice): bool
    {
        return in_array($invoice->status, [DocumentStatusEnum::DRAFT, DocumentStatusEnum::PREPARING], true)
            && ! tenancy()->initialized && $this->access->allows($actor, $invoice->document_type->issuePermission());
    }

    private function has(User $actor, string $ability): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, 'saas.invoices.'.$ability);
    }
}
