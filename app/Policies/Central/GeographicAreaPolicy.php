<?php

namespace App\Policies\Central;

use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class GeographicAreaPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
    }

    public function view(User $actor, GeographicArea $area): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->has($actor, 'create');
    }

    public function update(User $actor, GeographicArea $area): bool
    {
        return $this->canManage($actor, $area, 'update');
    }

    public function activate(User $actor, GeographicArea $area): bool
    {
        return $this->canManage($actor, $area, 'activate');
    }

    public function archive(User $actor, GeographicArea $area): bool
    {
        return $this->canManage($actor, $area, 'archive');
    }

    /** Une zone archivée n'est plus modifiable. */
    private function canManage(User $actor, GeographicArea $area, string $ability): bool
    {
        return ! $area->trashed() && $this->has($actor, $ability);
    }

    private function has(User $actor, string $ability): bool
    {
        return $this->access->allows($actor, "saas.geography.$ability");
    }
}