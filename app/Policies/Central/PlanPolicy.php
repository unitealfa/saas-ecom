<?php

namespace App\Policies\Central;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class PlanPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.plans.view');
    }

    public function view(User $actor, Plan $plan): bool
    {
        return $this->viewAny($actor);
    }
}
