<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\SaveRoleRequest;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\CentralRoleCatalog;
use App\Services\Central\RoleManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('central.pages.roles.index', ['roles' => Role::where('guard_name', 'central')->with('permissions')->orderBy('id')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return $this->form();
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);

        return $this->form($role);
    }

    public function store(SaveRoleRequest $request, RoleManager $roles): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $roles->save($actor, $request->toDto());

        return redirect()->route('central.roles.index')->with('status', 'Rôle créé.');
    }

    public function update(SaveRoleRequest $request, Role $role, RoleManager $roles): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $roles->save($actor, $request->toDto(), $role);

        return redirect()->route('central.roles.index')->with('status', 'Rôle enregistré.');
    }

    private function form(?Role $role = null): View
    {
        return view('central.pages.roles.form', [
            'role' => $role,
            'permissionGroups' => CentralRoleCatalog::GROUPS,
            'permissions' => Permission::where('guard_name', 'central')->whereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS))->orderBy('id')->get(),
            'grants' => $role?->permissions()->get()->mapWithKeys(fn ($permission): array => [$permission->getAttribute('uuid') => (int) $permission->getRelation('pivot')->getAttribute('duration_days')])->all() ?? [],
        ]);
    }
}
