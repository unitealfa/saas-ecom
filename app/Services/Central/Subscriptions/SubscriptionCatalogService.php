<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class SubscriptionCatalogService
{
    private const int OWNERS_PER_PAGE = 10;

    /** @return array{owners: LengthAwarePaginator<int, User>, selectedOwner: ?User} */
    public function assignmentOwners(User $actor, ?string $search, ?string $selectedUuid): array
    {
        Gate::forUser($actor)->authorize('assign', Subscription::class);
        $query = User::withStatus(UserStatus::ACTIVE)
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('guard_name', Role::GUARD))
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email']);
        $selected = $selectedUuid === null ? null : (clone $query)->where('uuid', $selectedUuid)->first();
        $search = trim((string) $search);
        if ($search !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn (Builder $person) => $person->where('first_name', 'like', $pattern)
                ->orWhere('last_name', 'like', $pattern)
                ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$pattern])
                ->orWhere('email', 'like', $pattern));
        }

        return [
            'owners' => $query->orderBy('first_name')->orderBy('last_name')->orderBy('id')
                ->paginate(self::OWNERS_PER_PAGE, pageName: 'ownersPage'),
            'selectedOwner' => $selected,
        ];
    }

    /** @return LengthAwarePaginator<int, Subscription> */
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

    /** @return LengthAwarePaginator<int, Subscription> */
    public function history(User $actor, Subscription $subscription): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('history', $subscription);

        return Subscription::query()->where('user_id', $subscription->user_id)
            ->with('plan')->orderByDesc('period_starts_at')->orderByDesc('id')->paginate(20);
    }
}
