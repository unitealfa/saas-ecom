<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Permission;
use App\Models\Central\User;
use App\Services\Central\Roles\CentralRoleCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class PermissionCatalogService
{
    private const int PER_PAGE = 20;

    public function paginate(User $actor, ?string $search = null): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Permission::class);

        $search = trim((string) $search);

        return Permission::forCentralGuard()
            ->when($search !== '', fn (Builder $query) => $this->matching($query, $search))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->appends($search === '' ? [] : ['search' => $search]);
    }

    public function groups(): array
    {
        return collect(CentralRoleCatalog::GROUPS)
            ->flatMap(fn (array $names, string $group): array => array_fill_keys($names, $group))
            ->all();
    }

    private function matching(Builder $query, string $search): Builder
    {
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return $query->where(function (Builder $query) use ($pattern): void {
            $query->where('name', 'like', $pattern)->orWhere('label', 'like', $pattern);
        });
    }
}