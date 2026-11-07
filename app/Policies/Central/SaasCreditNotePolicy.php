<?php

namespace App\Policies\Central;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class SaasCreditNotePolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, 'saas.credit-notes.view');
    }

    public function view(User $actor, SaasCreditNote $credit): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, 'saas.credit-notes.create');
    }

    public function inspectOriginal(User $actor, SaasInvoice $original): bool
    {
        return $this->viewAny($actor) || $this->create($actor)
            || (! tenancy()->initialized && $this->access->allows($actor, DocumentTypeEnum::CREDIT_NOTE->issuePermission()));
    }

    public function viewOwnCredit(User $actor, SaasCreditNote $credit): bool
    {
        return $this->viewOwn($actor) && $credit->status === DocumentStatusEnum::ISSUED && $credit->user_id === $actor->id;
    }

    public function update(User $actor, SaasCreditNote $credit): bool
    {
        return $credit->status === DocumentStatusEnum::DRAFT && $this->create($actor);
    }

    public function cancel(User $actor, SaasCreditNote $credit): bool
    {
        return in_array($credit->status, [DocumentStatusEnum::DRAFT, DocumentStatusEnum::PREPARING], true) && $this->create($actor);
    }

    public function issue(User $actor, SaasCreditNote $credit): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, DocumentTypeEnum::CREDIT_NOTE->issuePermission());
    }

    public function viewOwn(User $actor): bool
    {
        return ! tenancy()->initialized && ! $this->access->isStaff($actor);
    }

    public function download(User $actor, SaasCreditNote $credit): bool
    {
        return $credit->status === DocumentStatusEnum::ISSUED && ($this->view($actor, $credit)
            || ($this->viewOwn($actor) && $actor->id === $credit->user_id));
    }

    public function reserveNumber(User $actor, SaasCreditNote $credit): bool
    {
        return ! tenancy()->initialized
            && in_array($credit->status, [DocumentStatusEnum::DRAFT, DocumentStatusEnum::PREPARING], true)
            && $this->access->allows($actor, DocumentTypeEnum::CREDIT_NOTE->issuePermission());
    }
}
