<?php

namespace App\Policies\Central;

use App\Enums\Central\Billing\PolicyStatusEnum;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class BillingRulePolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
    }

    public function view(User $actor, BillingRule $rule): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->has($actor, 'create');
    }

    public function update(User $actor, BillingRule $rule): bool
    {
        return $this->has($actor, 'update') && $rule->policy_status === PolicyStatusEnum::DRAFT && ! $rule->isUsed();
    }

    public function activate(User $actor, BillingRule $rule): bool
    {
        return in_array($rule->policy_status, [PolicyStatusEnum::VALIDATED, PolicyStatusEnum::ACTIVE], true)
            && $this->has($actor, 'activate');
    }

    public function validateRule(User $actor, BillingRule $rule): bool
    {
        return $rule->policy_status === PolicyStatusEnum::DRAFT && $this->has($actor, 'activate');
    }

    public function createVersion(User $actor, BillingRule $rule): bool
    {
        return $this->has($actor, 'versions.create');
    }

    private function has(User $actor, string $action): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, 'saas.billing-rules.'.$action);
    }
}
