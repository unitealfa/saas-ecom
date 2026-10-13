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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StaffRoleController extends Controller
{
    public function edit(Request $request, User $user, UserManagementService $users): View
    {
        Gate::authorize('assignStaffRoles', $user);
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return view('central.pages.admin.staff.roles', [
            'user' => $user->load('roles'), 'roles' => $users->availableRoles($actor),
            'selectedRoles' => $user->roles->pluck('uuid')->all(),
        ]);
    }

    public function update(AssignStaffRolesRequest $request, User $user, CentralRoleAssignmentService $assignments): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $assignments->replace($actor, $user, $request->validated('roles', []));

        return redirect()->route('central.staff.index')->with('status', 'Rôles du personnel enregistrés.');
    }

    public function renew(RenewStaffAssignmentRequest $request, User $user, Role $role, CentralRoleAssignmentService $assignments): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $assignments->renewRole($actor, $user, $role, $request->validated('reason'), $request->validated('expected_assigned_at'));

        return redirect()->route('central.staff.show', $user)->with('status', 'Attribution renouvelée. Les durées du rôle repartent de maintenant.');
    }
}
