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
        return $this->access->allows($actor, 'saas.carriers.view');
    }

    public function view(User $actor, ShippingCarrier $carrier): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.carriers.create');
    }

    public function update(User $actor, ShippingCarrier $carrier): bool
    {
        return ! $carrier->trashed() && $this->access->allows($actor, 'saas.carriers.update');
    }

    public function activate(User $actor, ShippingCarrier $carrier): bool
    {
        return ! $carrier->trashed() && $this->access->allows($actor, 'saas.carriers.activate');
    }

    public function archive(User $actor, ShippingCarrier $carrier): bool
    {
        return ! $carrier->trashed() && $this->access->allows($actor, 'saas.carriers.archive');
    }
}
