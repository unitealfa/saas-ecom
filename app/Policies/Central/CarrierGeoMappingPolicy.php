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
        return $this->canManage($actor, $mapping, 'update');
    }

    public function archive(User $actor, CarrierGeoMapping $mapping): bool
    {
        return $this->canManage($actor, $mapping, 'archive');
    }

    public function import(User $actor): bool
    {
        return $this->has($actor, 'import');
    }

    /** Une correspondance archivée (inactive) n'est plus modifiable. */
    private function canManage(User $actor, CarrierGeoMapping $mapping, string $ability): bool
    {
        return $mapping->is_active && $this->has($actor, $ability);
    }

    private function has(User $actor, string $ability): bool
    {
        return $this->access->allows($actor, "saas.carrier-mappings.$ability");
    }
}
