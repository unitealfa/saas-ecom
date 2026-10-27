<?php

namespace App\Http\Controllers\Central\Admin\Roles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Roles\DeleteRoleRequest;
use App\Http\Requests\Central\Roles\SaveRoleRequest;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Roles\CentralRoleCatalog;
use App\Services\Central\Roles\RoleManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function __construct(private RoleManager $roles, private CentralUserAccess $access) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        $query = Role::forCentralGuard();
        if (Gate::allows('viewPermissions', new Role)) {
            $query->with('permissionComposition');
        }

        return view('central.pages.admin.roles.index', ['roles' => $query->orderBy('id')->paginate(20)]);
    }

    public function show(Role $role): View
    {
        Gate::authorize('view', $role);

        $permissions = Gate::allows('viewPermissions', $role)
            ? $role->permissionComposition()->orderBy('name')->get()
            : null;

        $assignments = Gate::allows('viewAssignments', $role) ? $this->assignments($role) : null;

        return view('central.pages.admin.roles.show', compact('role', 'permissions', 'assignments'));
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return $this->form();
    }

    public function edit(Role $role): View
    {
        Gate::authorize('edit', $role);

        return $this->form($role);
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        $this->roles->save($this->centralActor(), $request->toDto());

        return $this->back('Rôle créé.');
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->save($this->centralActor(), $request->toDto(), $role);

        return $this->back('Rôle enregistré.');
    }

    public function destroy(DeleteRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->delete($this->centralActor(), $role);

        return $this->back('Rôle supprimé.');
    }

    /** @return LengthAwarePaginator<int, User> */
    private function assignments(Role $role): LengthAwarePaginator
    {
        return User::withTrashed()
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email', 'status', 'deleted_at'])
            ->whereHas('roles', fn ($query) => $query->where('roles.id', $role->id))
            ->with(['roles' => fn ($query) => $query->where('roles.id', $role->id)])
            ->orderBy('id')
            ->paginate(20);
    }

    private function form(?Role $role = null): View
    {
        $actor = $this->centralActor();
        $canChangePermissions = $role === null || Gate::allows('updatePermissions', $role);
        $canChangeDurations = $role === null || Gate::allows('updateDurations', $role);

        // Chargée une seule fois, et seulement si le formulaire en a besoin.
        $composition = $role !== null && ($canChangePermissions || $canChangeDurations)
            ? $role->permissionComposition()->get()
            : collect();

        return view('central.pages.admin.roles.form', [
            'role' => $role,
            'permissionGroups' => CentralRoleCatalog::GROUPS,
            'permissions' => $this->selectablePermissions($actor, $canChangePermissions, $canChangeDurations, $composition),
            'canChangePermissions' => $canChangePermissions,
            'canChangeDurations' => $canChangeDurations,
            'grants' => $composition
                ->mapWithKeys(fn ($permission): array => [$permission->uuid => (int) $permission->pivot->duration_days])
                ->all(),
        ]);
    }

    /**
     * @param  Collection<int, Permission>  $composition
     * @return Collection<int, Permission>
     */
    private function selectablePermissions(User $actor, bool $canChangePermissions, bool $canChangeDurations, Collection $composition): Collection
    {
        if ($canChangePermissions) {
            return $this->access->ownedPermissions($actor);
        }

        return $canChangeDurations ? $composition : collect();
    }

    private function back(string $status): RedirectResponse
    {
        return redirect()->route(Gate::allows('viewAny', Role::class) ? 'central.roles.index' : 'dashboard')
            ->with('status', $status);
    }
}
