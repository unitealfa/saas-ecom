<?php

namespace App\Http\Controllers\Central\Admin\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\AssignStaffRolesRequest;
use App\Http\Requests\Central\Users\RenewStaffAssignmentRequest;
use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralRoleAssignmentService;
use App\Services\Central\Users\UserManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class StaffRoleController extends Controller
{
    public function __construct(private CentralRoleAssignmentService $assignments) {}

    public function edit(User $user, UserManagementService $users): View
    {
        Gate::authorize('assignStaffRoles', $user);

        return view('central.pages.admin.staff.roles', [
            'user' => $user->load('roles'),
            'roles' => $users->availableRoles($this->centralActor()),
            'selectedRoles' => $user->roles->pluck('uuid')->all(),
        ]);
    }

    public function update(AssignStaffRolesRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('assignStaffRoles', $user);

        $this->assignments->replace($this->centralActor(), $user, $request->validated('roles', []));

        return redirect()->route('central.staff.index')->with('status', 'Rôles du personnel enregistrés.');
    }

    public function renew(RenewStaffAssignmentRequest $request, User $user, Role $role): RedirectResponse
    {
        Gate::authorize('assignStaffRoles', $user);

        $this->assignments->renewRole(
            $this->centralActor(),
            $user,
            $role,
            $request->validated('reason'),
            $request->validated('expected_assigned_at'),
        );

        return redirect()->route('central.staff.show', $user)
            ->with('status', 'Attribution renouvelée. Les durées du rôle repartent de maintenant.');
    }
}