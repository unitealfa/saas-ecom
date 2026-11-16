<?php

namespace App\Policies\Central;

use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class BillingSequencePolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function initialize(User $actor, DocumentTypeEnum $type): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, $type->issuePermission());
    }
}
