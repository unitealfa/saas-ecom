<?php

namespace App\Http\Controllers\Central\Admin\Staff;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralPermissionResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StaffPermissionController extends Controller
{
    public function index(Request $request, User $user, CentralPermissionResolver $permissions): View
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return view('central.pages.admin.staff.permissions', [
            'user' => $user, 'permissions' => $permissions->forStaff($actor, $user),
        ]);
    }
}
