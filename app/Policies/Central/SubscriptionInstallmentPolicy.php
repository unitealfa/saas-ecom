<?php

namespace App\Policies\Central;

use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class SubscriptionInstallmentPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, 'saas.subscriptions.installments.view');
    }

    public function view(User $actor, SubscriptionInstallment $installment): bool
    {
        return $this->viewAny($actor);
    }
}
