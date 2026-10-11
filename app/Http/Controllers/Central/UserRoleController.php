<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\AssignRolesRequest;
use App\Models\User;
use App\Services\Central\UserManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserRoleController extends Controller
{
    public function edit(Request $request, User $user, UserManager $users): View
    {
        Gate::authorize('assignRoles', $user);
        $actor = $request->user('central');
        abort_unless($actor instanceof \App\Models\Central\User, 403);

        return view('central.pages.users.roles', [
            'user' => $user, 'roles' => $users->availableRoles($actor),
            'selectedRoles' => $user->roles()->pluck('uuid')->all(),
        ]);
    }

    public function update(AssignRolesRequest $request, User $user, UserManager $users): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof \App\Models\Central\User, 403);
        $users->assignRoles($actor, $user, $request->validated('roles', []));

        $destination = Gate::forUser($actor)->allows('viewAny', User::class) ? 'central.users.index' : 'dashboard';

        return redirect()->route($destination)->with('status', 'Rôles enregistrés.');
    }
}
