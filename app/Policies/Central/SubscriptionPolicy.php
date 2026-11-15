<?php

namespace App\Policies\Central;

use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class SubscriptionPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function purchase(User $actor): bool
    {
        return ! tenancy()->initialized && ! $this->access->isStaff($actor)
            && $actor->status === StatusEnum::ACTIVE && $actor->hasVerifiedEmail()
            && $actor->legal_verification_status === VerificationStatusEnum::VERIFIED
            && $actor->legal_verified_at !== null;
    }

    public function purchaseFor(User $actor, User $owner, ?Subscription $source = null): bool
    {
        if ($owner->status !== StatusEnum::ACTIVE || $this->access->isStaff($owner) || tenancy()->initialized) {
            return false;
        }

        if ($actor->id === $owner->id) {
            return $this->purchase($actor);
        }

        return $source === null ? $this->assign($actor) : ($source->user_id === $owner->id && $this->change($actor, $source));
    }

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
