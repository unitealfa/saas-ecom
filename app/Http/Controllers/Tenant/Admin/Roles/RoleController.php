<?php

namespace App\Http\Controllers\Tenant\Admin\Roles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Roles\SaveRoleRequest;
use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Roles\Role;
use App\Services\Tenant\Roles\RoleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\View\View;

final class RoleController extends Controller
{
    public function __construct(private readonly RoleManager $roles) {}

    public function index(Request $request): View
    {
        Gate::forUser($request->user('tenant'))->authorize('viewAny', Role::class);
        $query = Role::query()->withCount('users')->orderBy('name');
        if ($request->filled('search')) {
            $search = substr((string) $request->query('search'), 0, 100);
            $query->where('name', 'like', '%'.addcslashes($search, '\\%_').'%');
        }
        return view('tenant.pages.admin.roles.index', ['roles' => $query->paginate(20)->withQueryString()]);
    }

    public function create(Request $request): View
    {
        Gate::forUser($request->user('tenant'))->authorize('create', Role::class);
        return $this->form(null);
    }

    public function edit(Request $request, Role $role): View
    {
        Gate::forUser($request->user('tenant'))->authorize('update', $role);
        return $this->form($role);
    }

    private function form(?Role $role): View
    {
        return view('tenant.pages.admin.roles.form', [
            'role' => $role,
            'permissions' => Permission::query()->orderBy('name')->get(),
            'selected' => $role?->permissionComposition()->get()->pluck('pivot.duration_days', 'uuid')->all() ?? [],
            'expected' => $role === null ? null : $this->roles->state($role),
        ]);
    }

    public function show(Request $request, Role $role): View
    {
        Gate::forUser($request->user('tenant'))->authorize('view', $role);
        return view('tenant.pages.admin.roles.show', ['role' => $role->load('permissions'),
            'members' => $role->users()->paginate(20)]);
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        $role = $this->roles->save($request->user('tenant'), null, $request->validated());
        return to_route('tenant.admin.roles.show', $role)->with('status', 'Profil créé.');
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        $role = $this->roles->save($request->user('tenant'), $role, $request->validated());
        return to_route('tenant.admin.roles.show', $role)->with('status', 'Profil modifié.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $request->validate(['expected_state' => ['required', 'string', 'size:64']]);
        $this->roles->delete($request->user('tenant'), $role, $request->string('expected_state')->toString());
        return to_route('tenant.admin.roles.index')->with('status', 'Profil supprimé.');
    }
}
