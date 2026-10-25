<?php

namespace App\Http\Controllers\Central\Admin\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\UpdateStaffPermissionsRequest;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralPermissionAssignmentService;
use App\Services\Central\Authorization\CentralPermissionResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class StaffPermissionController extends Controller
{
    public function __construct(
        private CentralPermissionAssignmentService $assignments,
        private CentralPermissionResolver $permissions,
    ) {}

    public function index(User $user): View
    {
        Gate::authorize('viewStaffPermissions', $user);

        return view('central.pages.admin.staff.permissions', [
            'user' => $user,
            'permissions' => $this->permissions->forStaff($this->centralActor(), $user),
            'expectedState' => $this->assignments->version($user),
        ]);
    }

    public function update(UpdateStaffPermissionsRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('updateStaffPermissions', $user);

        $data = $request->validated();

        $this->assignments->update(
            $this->centralActor(),
            $user,
            $data['grants'],
            $data['expected_state'],
            $request->validated('reason'),
        );

        return redirect()
            ->route('central.staff.permissions.index', $user)
            ->with('status', 'Permissions individuelles enregistrées.');
    }
}
