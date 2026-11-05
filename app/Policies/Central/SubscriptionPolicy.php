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

    public function assign(User $actor): bool
    {
        return $this->access->allows($actor, 'saas.subscriptions.assign');
    }

    public function change(User $actor, Subscription $subscription): bool
    {
        return $this->access->allows($actor, 'saas.subscriptions.change');
    }

    public function close(User $actor, Subscription $subscription): bool
    {
        return $this->access->allows($actor, 'saas.subscriptions.close');
    }

    public function createInstallment(User $actor, Subscription $subscription): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, 'saas.subscriptions.installments.create');
    }

    public function deactivate(User $actor, Subscription $subscription): bool
    {
        return $this->access->allows($actor, 'saas.subscriptions.deactivate');
    }

    public function history(User $actor, Subscription $subscription): bool
    {
        return $this->access->allows($actor, 'saas.subscriptions.history.view');
    }
}
