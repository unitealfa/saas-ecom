<?php

namespace App\Http\Controllers\Central\Admin\Roles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Roles\DeleteRoleRequest;
use App\Http\Requests\Central\Roles\SaveRoleRequest;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Roles\CentralRoleCatalog;
use App\Services\Central\Roles\RoleManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function __construct(private RoleManager $roles, private CentralUserAccess $access) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        $query = Role::where('guard_name', 'central');
        if (Gate::allows('viewPermissions', new Role)) {
            $query->with('permissions');
        }

        return view('central.pages.admin.roles.index', ['roles' => $query->orderBy('id')->paginate(20)]);
    }

    public function show(Role $role): View
    {
        Gate::authorize('view', $role);
        $permissions = Gate::allows('viewPermissions', $role) ? $role->permissions()->orderBy('name')->get() : null;
        $assignments = null;
        if (Gate::allows('viewAssignments', $role)) {
            $assignments = User::withTrashed()->select(['id', 'uuid', 'first_name', 'last_name', 'email', 'status', 'deleted_at'])
                ->whereHas('roles', fn ($query) => $query->where('roles.id', $role->id))
                ->with(['roles' => fn ($query) => $query->where('roles.id', $role->id)])
                ->orderBy('id')->paginate(20);
        }

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
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $this->roles->save($actor, $request->toDto());

        return redirect()->route('central.roles.index')->with('status', 'Rôle créé.');
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $this->roles->save($actor, $request->toDto(), $role);

        return redirect()->route('central.roles.index')->with('status', 'Rôle enregistré.');
    }

    public function destroy(DeleteRoleRequest $request, Role $role): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $this->roles->delete($actor, $role);

        return redirect()->route('central.roles.index')->with('status', 'Rôle supprimé.');
    }

    private function form(?Role $role = null): View
    {
        $actor = auth('central')->user();
        abort_unless($actor instanceof User, 403);
        $canChangePermissions = $role === null || Gate::allows('updatePermissions', $role);
        $canChangeDurations = $role === null || Gate::allows('updateDurations', $role);
        $permissions = collect();
        if ($canChangePermissions) {
            $permissions = Permission::where('guard_name', 'central')->whereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS))
                ->orderBy('id')->get()->filter(fn (Permission $permission): bool => $this->access->ownsPermission($actor, $permission->name));
        } elseif ($canChangeDurations) {
            $permissions = $role->permissions()->orderBy('permissions.id')->get();
        }

        return view('central.pages.admin.roles.form', [
            'role' => $role,
            'permissionGroups' => CentralRoleCatalog::GROUPS,
            'permissions' => $permissions,
            'canChangePermissions' => $canChangePermissions,
            'canChangeDurations' => $canChangeDurations,
            'grants' => ($canChangePermissions || $canChangeDurations) ? ($role?->permissions()->get()->mapWithKeys(fn ($permission): array => [$permission->uuid => (int) $permission->pivot->duration_days])->all() ?? []) : [],
        ]);
    }
}
