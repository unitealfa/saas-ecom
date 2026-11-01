<?php

namespace App\Policies\Central;

use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class ShippingCarrierPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
    }

    public function view(User $actor, ShippingCarrier $carrier): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->has($actor, 'create');
    }

    public function update(User $actor, ShippingCarrier $carrier): bool
    {
        return $this->canManage($actor, $carrier, 'update');
    }

    public function activate(User $actor, ShippingCarrier $carrier): bool
    {
        return $this->canManage($actor, $carrier, 'activate');
    }

    public function archive(User $actor, ShippingCarrier $carrier): bool
    {
        return $this->canManage($actor, $carrier, 'archive');
    }

    /** Un transporteur archivé n'est plus modifiable. */
    private function canManage(User $actor, ShippingCarrier $carrier, string $ability): bool
    {
        return ! $carrier->trashed() && $this->has($actor, $ability);
    }

    private function has(User $actor, string $ability): bool
    {
        return $this->access->allows($actor, "saas.carriers.$ability");
    }
}
