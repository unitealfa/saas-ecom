<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class PlanCatalogService
{
    private const int PER_PAGE = 20;

    /** @return LengthAwarePaginator<int, Plan> */
    public function paginate(User $actor, ?string $search = null, ?string $sort = null, ?string $direction = null): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Plan::class);

        $search = trim((string) $search);
        $pattern = '%'.addcslashes($search, '\\%_').'%';
        $sort = in_array($sort, ['name', 'version', 'monthly_price', 'annual_price', 'is_active'], true) ? $sort : null;
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return Plan::withTrashed()->with('features')
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('code', 'like', $pattern)->orWhere('name', 'like', $pattern),
            ))
            ->when($sort !== null, fn (Builder $query) => $query->orderBy($sort, $direction))
            ->orderBy('code')->orderByDesc('version')->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->appends(['search' => $search === '' ? null : $search, 'sort' => $sort, 'direction' => $sort === null ? null : $direction]);
    }

    public function details(User $actor, Plan $plan): Plan
    {
        Gate::forUser($actor)->authorize('view', $plan);

        return $plan->load(['features.feature' => fn (BelongsTo $relation) => $relation->getQuery()->withoutGlobalScope(SoftDeletingScope::class)]);
    }

    public function latestVersion(User $actor, Plan $plan): int
    {
        Gate::forUser($actor)->authorize('view', $plan);

        return (int) Plan::withTrashed()->where('code', $plan->code)->max('version');
    }
}
