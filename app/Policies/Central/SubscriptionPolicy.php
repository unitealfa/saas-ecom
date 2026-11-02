<?php

namespace App\Policies\Central;

use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class SubscriptionPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.subscriptions.view');
    }

    public function view(User $actor, Subscription $subscription): bool
    {
        return $this->viewAny($actor);
    }
}
