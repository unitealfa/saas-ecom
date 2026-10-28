<?php

namespace App\Policies\Central;

use App\Models\Central\Features\Feature;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class FeaturePolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
    }

    public function view(User $actor, Feature $feature): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->has($actor, 'create');
    }

    public function update(User $actor, Feature $feature): bool
    {
        return $this->canManage($actor, $feature, 'update');
    }

    public function activate(User $actor, Feature $feature): bool
    {
        return ! $feature->isSystem() && $this->canManage($actor, $feature, 'activate');
    }

    public function archive(User $actor, Feature $feature): bool
    {
        return ! $feature->isSystem() && $this->canManage($actor, $feature, 'archive');
    }

    /** Une fonctionnalité archivée n'est plus modifiable. */
    private function canManage(User $actor, Feature $feature, string $ability): bool
    {
        return ! $feature->trashed() && $this->has($actor, $ability);
    }

    private function has(User $actor, string $ability): bool
    {
        return $this->access->allows($actor, "saas.features.$ability");
    }
}