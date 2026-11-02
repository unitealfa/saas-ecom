<?php

namespace App\Services\Central\Subscriptions;

use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class SubscriptionCatalogService
{
    public function paginate(User $actor, ?string $search, ?int $status): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Subscription::class);
        $search = trim((string) $search);
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return Subscription::query()->with(['owner', 'plan'])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($pattern): void {
                $query->where('uuid', 'like', $pattern)
                    ->orWhereHas('owner', fn (Builder $owner) => $owner->where('first_name', 'like', $pattern)
                        ->orWhere('last_name', 'like', $pattern)
                        ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$pattern])
                        ->orWhere('email', 'like', $pattern))
                    ->orWhereHas('plan', fn (Builder $plan) => $plan->where('name', 'like', $pattern)->orWhere('code', 'like', $pattern));
            }))
            ->orderByDesc('id')->paginate(20)->withQueryString();
    }

    public function details(User $actor, Subscription $subscription): Subscription
    {
        Gate::forUser($actor)->authorize('view', $subscription);

        return $subscription->load(['owner', 'plan', 'assignedBy']);
    }
}
