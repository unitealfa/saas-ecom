<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class PlanCatalogService
{
    private const int PER_PAGE = 20;

    /** @return LengthAwarePaginator<int, Plan> */
    public function paginate(User $actor, ?string $search = null): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Plan::class);
        $search = trim((string) $search);
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return Plan::withTrashed()
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('code', 'like', $pattern)->orWhere('name', 'like', $pattern),
            ))
            ->orderBy('code')->orderByDesc('version')->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->appends($search === '' ? [] : ['search' => $search]);
    }

    public function details(User $actor, Plan $plan): Plan
    {
        Gate::forUser($actor)->authorize('view', $plan);

        return $plan->load(['features.feature' => fn (BelongsTo $relation) => $relation->withTrashed()]);
    }

    public function latestVersion(User $actor, Plan $plan): int
    {
        Gate::forUser($actor)->authorize('view', $plan);

        return (int) Plan::withTrashed()->where('code', $plan->code)->max('version');
    }
}
