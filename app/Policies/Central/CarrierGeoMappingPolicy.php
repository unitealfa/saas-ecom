<?php

namespace App\Policies\Central;

use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class CarrierGeoMappingPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
    }

    public function create(User $actor): bool
    {
        return $this->has($actor, 'create');
    }

    public function update(User $actor, CarrierGeoMapping $mapping): bool
    {
        return $mapping->is_active && $this->has($actor, 'update');
    }

    public function archive(User $actor, CarrierGeoMapping $mapping): bool
    {
        return $mapping->is_active && $this->has($actor, 'archive');
    }

    public function import(User $actor): bool
    {
        return $this->has($actor, 'import');
    }

    private function has(User $actor, string $action): bool
    {
        return $this->access->allows($actor, 'saas.carrier-mappings.'.$action);
    }
}
