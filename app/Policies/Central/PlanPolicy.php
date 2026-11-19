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

    public function setDefault(User $actor, Plan $plan): bool
    {
        return ! tenancy()->initialized && ! $plan->trashed() && $plan->is_active
            && $this->access->allows($actor, 'saas.plans.activate')
            && (($plan->monthly_price === '0.00' && $plan->annual_price === '0.00')
                || $this->access->allows($actor, 'saas.plans.versions.create'));
    }

    public function create(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.plans.create');
    }

    /** Seuls les quotas équipe du plan gratuit peuvent être versionnés séparément. */
    public function configureTeamQuotas(User $actor, Plan $plan): bool
    {
        return ! $plan->trashed() && $plan->is_active && strtolower($plan->code) === 'free'
            && $this->access->allows($actor, 'saas.plans.features.update')
            && $this->access->allows($actor, 'saas.plans.versions.create');
    }

    public function activateTeamQuotaVersion(User $actor, Plan $plan): bool
    {
        return ! $plan->trashed() && ! $plan->is_active && strtolower($plan->code) === 'free'
            && $this->access->allows($actor, 'saas.plans.activate');
    }

    public function updateFeatures(User $actor, Plan $plan): bool
    {
        return ! $plan->is_default && ! $plan->trashed() && strtolower($plan->code) !== 'free'
            && $this->access->allows($actor, 'saas.plans.features.update');
    }

    public function update(User $actor, Plan $plan): bool
    {
        return ! $plan->trashed() && strtolower($plan->code) !== 'free'
            && $this->access->allows($actor, 'saas.plans.update');
    }

    public function createVersion(User $actor, Plan $plan): bool
    {
        return ! $plan->trashed() && strtolower($plan->code) !== 'free'
            && $this->access->allows($actor, 'saas.plans.versions.create');
    }

    public function activate(User $actor, Plan $plan): bool
    {
        return ! $plan->is_default && ! $plan->trashed() && strtolower($plan->code) !== 'free'
            && $this->access->allows($actor, 'saas.plans.activate');
    }

    public function archive(User $actor, Plan $plan): bool
    {
        return ! $plan->is_default && ! $plan->trashed() && strtolower($plan->code) !== 'free'
            && $this->access->allows($actor, 'saas.plans.archive');
    }
}
