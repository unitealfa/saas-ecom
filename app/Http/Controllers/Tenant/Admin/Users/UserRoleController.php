<?php

namespace App\Http\Controllers\Tenant\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Users\ChangeUserRoleRequest;
use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Users\UserRoleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class UserRoleController extends Controller
{
    public function __construct(private readonly UserRoleService $roles) {}

    public function show(Request $request, User $member): View
    {
        Gate::forUser($request->user('tenant'))->authorize('view', $member);
        Gate::forUser($request->user('tenant'))->authorize('viewAny', Role::class);
        abort_unless($member->central_user_uuid === null, 404);

        return view('tenant.pages.admin.users.roles', ['member' => $member,
            'roles' => Role::orderBy('name')->get(),
            'assigned' => $member->roles()->pluck('roles.id')->all(),
            'state' => $this->roles->state($member)]);
    }

    public function change(ChangeUserRoleRequest $request, User $member): RedirectResponse
    {
        $data = $request->validated();
        $role = Role::where('uuid', $data['role'])->firstOrFail();
        $this->roles->change($request->user('tenant'), $member, $role, $data['action'], $data['expected_state']);
        $gate = Gate::forUser($request->user('tenant'));

        return ($gate->allows('view', $member) && $gate->allows('viewAny', Role::class)
            ? to_route('tenant.admin.users.roles', $member) : to_route('tenant.dashboard'))
            ->with('status', 'Profils mis à jour.');
    }
}
