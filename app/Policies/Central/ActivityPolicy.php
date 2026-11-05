<?php

namespace App\Policies\Central;

use App\Models\Central\Users\User;
use App\Models\Central\Audit\Activity;
use App\Services\Central\Authorization\CentralUserAccess;

class ActivityPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.activity.view');
    }

    public function export(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.activity.export');
    }

    public function view(User $actor, Activity $activity): bool
    {
        return $this->viewAny($actor);
    }
}
