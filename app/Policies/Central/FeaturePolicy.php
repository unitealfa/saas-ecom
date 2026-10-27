<?php

namespace App\Policies\Central;

use App\Models\Central\Features\Feature;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class FeaturePolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.features.view');
    }

    public function view(User $actor, Feature $feature): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.features.create');
    }

    public function update(User $actor, Feature $feature): bool
    {
        return ! $feature->trashed() && $this->access->allows($actor, 'saas.features.update');
    }

    public function activate(User $actor, Feature $feature): bool
    {
        return ! $feature->trashed() && ! $feature->isSystem() && $this->access->allows($actor, 'saas.features.activate');
    }

    public function archive(User $actor, Feature $feature): bool
    {
        return ! $feature->trashed() && ! $feature->isSystem() && $this->access->allows($actor, 'saas.features.archive');
    }
}
