<?php

namespace App\Policies\Central;

use App\Models\Central\User;
use App\Services\Central\CentralUserAccess;

class TenantPolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function create(User $actor, User $owner): bool
    {
        return $this->access->isSuperAdmin($actor)
            && ! $this->access->isStaff($owner)
            && $this->access->allows($actor, 'saas.users.create', $owner);
    }
}
