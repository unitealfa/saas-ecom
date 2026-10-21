<?php

namespace App\Http\Controllers\Central\Admin\Staff;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralPermissionResolver;
use App\Services\Central\Authorization\CentralPermissionAssignmentService;
use App\Http\Requests\Central\Users\UpdateStaffPermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StaffPermissionController extends Controller
{
    public function __construct(private CentralPermissionAssignmentService $assignments) {}

    public function index(Request $request, User $user, CentralPermissionResolver $permissions): View
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return view('central.pages.admin.staff.permissions', [
            'user' => $user, 'permissions' => $permissions->forStaff($actor, $user),
            'expectedState' => $this->assignments->version($user),
        ]);
    }

    public function update(UpdateStaffPermissionsRequest $request, User $user): RedirectResponse
    {
        $this->assignments->update($request->user('central'), $user, $request->validated('grants'),
            $request->validated('expected_state'), $request->validated('reason'));

        return redirect()->route('central.staff.permissions.index', $user)->with('status', 'Permissions individuelles enregistrées.');
    }
}
