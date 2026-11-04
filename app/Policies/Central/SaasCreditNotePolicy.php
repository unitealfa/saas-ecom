<?php

namespace App\Policies\Central;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

/** Le Module 20 définira séparément la création et l'émission complète des avoirs. */
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

    public function reserveNumber(User $actor, SaasCreditNote $credit): bool
    {
        return ! tenancy()->initialized
            && in_array($credit->status, [DocumentStatusEnum::DRAFT, DocumentStatusEnum::PREPARING], true)
            && $this->access->allows($actor, DocumentTypeEnum::CREDIT_NOTE->issuePermission());
    }
}
