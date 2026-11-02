<?php

namespace App\Policies\Central;

use App\Models\Central\Shipping\PickupPoint;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class PickupPointPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
    }

    public function view(User $actor, PickupPoint $point): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->has($actor, 'create');
    }

    public function import(User $actor): bool
    {
        return $this->has($actor, 'import');
    }

    public function update(User $actor, PickupPoint $point): bool
    {
        return $this->canManage($actor, $point, 'update');
    }

    public function activate(User $actor, PickupPoint $point): bool
    {
        return $this->canManage($actor, $point, 'activate');
    }

    public function archive(User $actor, PickupPoint $point): bool
    {
        return $this->canManage($actor, $point, 'archive');
    }

    private function canManage(User $actor, PickupPoint $point, string $ability): bool
    {
        return ! $point->trashed() && $this->has($actor, $ability);
    }

    private function has(User $actor, string $ability): bool
    {
        return $this->access->allows($actor, "saas.pickup-points.$ability");
    }
}
