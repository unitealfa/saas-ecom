<?php

namespace App\Policies\Central;

use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Models\Central\Billing\BillingSequence;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class BillingSequencePolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, 'saas.billing-sequences.view');
    }

    public function view(User $actor, BillingSequence $sequence): bool
    {
        return $this->viewAny($actor);
    }

    public function initialize(User $actor, DocumentTypeEnum $type): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, $type->issuePermission());
    }
}
