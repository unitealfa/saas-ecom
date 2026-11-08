<?php

namespace App\Policies\Central;

use App\Enums\Central\Subscriptions\RequestStatusEnum;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;
use Illuminate\Support\Facades\Gate;

class SubscriptionRequestPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return ! tenancy()->initialized && ($this->access->allows($actor, 'saas.subscriptions.view') || $this->access->allows($actor, 'saas.payments.view'));
    }

    public function create(User $actor): bool
    {
        return ! tenancy()->initialized && Gate::forUser($actor)->allows('purchase', Subscription::class);
    }

    public function createFor(User $actor, User $owner): bool
    {
        return ! tenancy()->initialized && Gate::forUser($actor)->allows('purchaseFor', [Subscription::class, $owner]);
    }

    public function view(User $actor, SubscriptionRequest $request): bool
    {
        return $this->viewAny($actor) || $this->owns($actor, $request);
    }

    public function update(User $actor, SubscriptionRequest $request): bool
    {
        return $this->owns($actor, $request) && $this->create($actor) && ! $request->status->isFinal()
            && $request->payment?->hasReportedFunds() !== true;
    }

    public function cancel(User $actor, SubscriptionRequest $request): bool
    {
        return $this->update($actor, $request);
    }

    public function prepareInvoice(User $actor, SubscriptionRequest $request): bool
    {
        return ! $request->status->isFinal() && ($this->update($actor, $request)
            || (! tenancy()->initialized && $this->access->allows($actor, 'saas.invoices.create') && $this->access->allows($actor, 'saas.invoices.issue')));
    }

    public function accept(User $actor, SubscriptionRequest $request): bool
    {
        return ! tenancy()->initialized && $actor->id !== $request->user_id && ! $request->status->isFinal()
            && $this->access->allows($actor, 'saas.payments.validate');
    }

    public function reject(User $actor, SubscriptionRequest $request): bool
    {
        return ! tenancy()->initialized && $actor->id !== $request->user_id && ! $request->status->isFinal()
            && $request->status !== RequestStatusEnum::REJECTED && $request->payment?->hasReportedFunds() !== true
            && $this->access->allows($actor, 'saas.payments.reject');
    }

    public function history(User $actor, SubscriptionRequest $request): bool
    {
        return $this->owns($actor, $request) || ($this->viewAny($actor)
            && ($this->access->allows($actor, 'saas.subscriptions.history.view') || $this->access->allows($actor, 'saas.payments.view')));
    }

    private function owns(User $actor, SubscriptionRequest $request): bool
    {
        return ! tenancy()->initialized && $request->user_id === $actor->id && ! $this->access->isStaff($actor);
    }
}
