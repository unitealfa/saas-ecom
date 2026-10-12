<?php

namespace App\Policies\Central;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;

class TenantPolicy
{
    public function __construct(private CentralUserAccess $access) {}

    public function create(User $actor, User $owner): bool
    {
        return $this->access->isSuperAdmin($actor)
            && ! $owner->trashed() && $owner->status === StatusEnum::ACTIVE
            && ! $this->access->isStaff($owner)
            && $this->access->allows($actor, 'saas.users.create', $owner);
    }
}
