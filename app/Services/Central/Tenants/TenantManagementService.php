<?php

namespace App\Services\Central\Tenants;

use App\Enums\Central\Subscriptions\StatusEnum as SubscriptionStatus;
use App\Enums\Central\Tenants\StatusEnum;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Jobs\Central\SyncTenantProfile;
use App\Models\Central\Activity;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class TenantManagementService
{
    public function __construct(private AuthorizationMutation $mutations) {}

    /** @return LengthAwarePaginator<int, Tenant> */
    public function search(User $actor, ?string $search, ?StatusEnum $status): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Tenant::class);
        $query = Tenant::with(['owner' => fn ($query) => $query->withTrashed(), 'domains']);
        if ($status === StatusEnum::DELETED) {
            $query->onlyTrashed();
        }
        if ($status !== null) {
            $query->where('status', $status);
        }
        if (filled($search)) {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($query) use ($pattern): void {
                $query->where('shop_name', 'like', $pattern)->orWhere('slug', 'like', $pattern)
                    ->orWhereHas('owner', fn ($owner) => $owner->withTrashed()->where(function ($owner) use ($pattern): void {
                        $owner->where('first_name', 'like', $pattern)->orWhere('last_name', 'like', $pattern)->orWhere('email', 'like', $pattern);
                    }))
                    ->orWhereHas('domains', fn ($domain) => $domain->where('domain', 'like', $pattern));
            });
        }

        return $query->orderBy('id')->paginate(20)->appends(['search' => $search, 'status' => $status?->value]);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function owners(User $actor, ?string $search): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('create', Tenant::class);
        $query = User::withStatus(UserStatus::ACTIVE)->whereDoesntHave('roles', fn ($roles) => $roles->where('guard_name', 'central'));
        if (filled($search)) {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn ($query) => $query->where('first_name', 'like', $pattern)->orWhere('last_name', 'like', $pattern)->orWhere('email', 'like', $pattern));
        }

        return $query->orderBy('id')->paginate(20)->appends(['search' => $search]);
    }

    /** @param Collection<int, Tenant> $tenants
     * @return Collection<int, Subscription>
     */
    public function subscriptions(Collection $tenants): Collection
    {
        return Subscription::whereIn('user_id', $tenants->pluck('user_id')->unique())
            ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::TRIAL])
            ->where('period_starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('period_ends_at')->orWhere('period_ends_at', '>', now()))
            ->with('plan')->orderByDesc('id')->get()->unique('user_id')->keyBy('user_id');
    }

    /** @return LengthAwarePaginator<int, Activity> */
    public function history(User $actor, Tenant $tenant): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewHistory', $tenant);

        return Activity::forSubject($tenant)->with('causer')->orderByDesc('id')->paginate(20);
    }

    /** @param array{shop_name: string, internal_label: string, expected_profile_version: int|string} $data */
    public function update(User $actor, Tenant $tenant, array $data): void
    {
        $this->mutations->run(function () use ($actor, $tenant, $data): void {
            User::withTrashed()->whereKey($tenant->user_id)->lockForUpdate()->firstOrFail();
            $tenant = Tenant::whereKey($tenant->id)->where('uuid', $tenant->uuid)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $tenant);
            if ($tenant->profile_version !== (int) $data['expected_profile_version']) {
                throw ValidationException::withMessages(['tenant' => 'La boutique a changé. Rechargez sa fiche.']);
            }
            $before = $tenant->only(['shop_name', 'internal_label', 'profile_version']);
            $tenant->fill(['shop_name' => $data['shop_name'], 'internal_label' => $data['internal_label']]);
            $renamed = $tenant->isDirty('shop_name');
            if (! $tenant->isDirty()) {
                return;
            }
            if ($renamed) {
                $tenant->profile_version++;
            }
            $tenant->save();
            if ($renamed) {
                if (config('queue.connections.central-provisioning.connection') !== $tenant->getConnectionName()) {
                    throw new LogicException('Profile projections require the central database queue.');
                }
                SyncTenantProfile::dispatch($tenant->id, $tenant->uuid)->beforeCommit();
            }
            activity()->causedBy($actor)->performedOn($tenant)->event('tenant_updated')
                ->withProperties(['before' => $before, 'after' => $tenant->only(array_keys($before))])->log('Informations centrales de la boutique modifiées.');
        });
    }
}
