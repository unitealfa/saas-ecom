<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreUserRequest;
use App\Models\Central\Country;
use App\Models\Central\User;
use App\Services\Central\UserManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function create(Request $request, UserManager $users): View
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        abort_unless(Gate::forUser($actor)->allows('create', User::class)
            || Gate::forUser($actor)->allows('createStaff', User::class), 403);

        return view('central.pages.users.create', [
            'countries' => Country::where('is_active', true)->orderBy('name_fr')->get(),
            'roles' => $users->availableRoles($actor),
            'canCreateMerchant' => Gate::forUser($actor)->allows('create', User::class),
            'canCreateStaff' => Gate::forUser($actor)->allows('createStaff', User::class),
        ]);
    }

    public function store(StoreUserRequest $request, UserManager $users): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $user = $users->create($actor, $request->toDto());

        $destination = Gate::forUser($actor)->allows('viewAny', User::class) ? 'central.users.index' : 'dashboard';

        return redirect()->route($destination)->with('status', 'Compte créé pour '.$user->name.'. L’invitation sera envoyée par la file d’attente.');
    }
}
