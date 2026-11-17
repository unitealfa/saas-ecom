<?php

namespace App\Http\Controllers\Tenant\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Users\ChangeUserPermissionRequest;
use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Users\UserPermissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class UserPermissionController extends Controller
{
    public function __construct(private readonly UserPermissionService $permissions) {}
    public function show(Request $request, User $member): View
    {
        Gate::forUser($request->user('tenant'))->authorize('view', $member);
        abort_unless($member->central_user_uuid === null, 404);
        return view('tenant.pages.admin.users.permissions', ['member' => $member,
            'catalog' => Permission::orderBy('name')->get(),
            'grants' => $member->permissions()->get()->keyBy('uuid'),
            'state' => $this->permissions->state($member)]);
    }
    public function change(ChangeUserPermissionRequest $request, User $member): RedirectResponse
    {
        $data = $request->validated();
        $permission = Permission::where('uuid', $data['permission'])->firstOrFail();
        $this->permissions->change($request->user('tenant'), $member, $permission,
            $data['action'], (int) ($data['days'] ?? 1), $data['expected_state'], $data['reason']);
        return to_route('tenant.admin.users.permissions', $member)->with('status', 'Droits mis à jour.');
    }
}
