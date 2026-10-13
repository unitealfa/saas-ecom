<?php

namespace App\Services\Central\Authorization;

use App\Models\Central\Permission;
use App\Models\Central\User;
use App\Services\Central\Roles\CentralRoleCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class PermissionCatalogService
{
    public function __construct(private AuthorizationMutation $mutations) {}

    /** @return LengthAwarePaginator<int, Permission> */
    public function paginate(User $actor, ?string $search = null): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Permission::class);
        $query = Permission::where('guard_name', 'central');
        if ($search !== null && $search !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($query) use ($pattern): void {
                $query->where('name', 'like', $pattern)->orWhere('label', 'like', $pattern);
            });
        }

        return $query->orderBy('name')->orderBy('id')->paginate(20)->appends(['search' => $search]);
    }

    /** @return array<string, string> Code => groupe */
    public function groups(): array
    {
        return collect(CentralRoleCatalog::GROUPS)->flatMap(fn (array $names, string $group): array => array_fill_keys($names, $group))->all();
    }

    public function updateLabel(User $actor, Permission $permission, string $label): void
    {
        Gate::forUser($actor)->authorize('update', $permission);
        $this->mutations->run(function () use ($actor, $permission, $label): void {
            $permission = Permission::whereKey($permission->id)->where('uuid', $permission->uuid)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $permission);
            $before = $permission->label;
            $permission->label = $label;
            if (! $permission->isDirty('label')) {
                return;
            }
            $permission->save();
            activity()->causedBy($actor)->performedOn($permission)->event('permission_label_updated')
                ->withProperties(['code' => $permission->name, 'before' => ['label' => $before], 'after' => ['label' => $label]])
                ->log('Libellé de la permission centrale modifié.');
        });
    }
}
