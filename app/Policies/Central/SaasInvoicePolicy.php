<?php

namespace App\Policies\Central;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class SaasInvoicePolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
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
