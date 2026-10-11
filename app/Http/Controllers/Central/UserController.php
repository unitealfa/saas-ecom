<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreUserRequest;
use App\Http\Requests\Central\UpdateUserRequest;
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
        $result = $users->create($actor, $request->toDto());

        $destination = Gate::forUser($actor)->allows('viewAny', User::class) ? 'central.users.index' : 'dashboard';

        $response = redirect()->route($destination)->with('status', 'Compte créé pour '.$result->user->name.'.');
        if ($result->emailSent === true) {
            return $response->with('status', 'Compte créé pour '.$result->user->name.'. L’invitation a été envoyée.');
        }

        return $response->with('warning', 'Le compte est créé, mais l’invitation n’a pas pu être envoyée. Vérifiez le SMTP puis renvoyez l’invitation depuis la fiche du compte.');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);

        return view('central.pages.users.show', [
            'user' => $user->load(['country', 'roles']),
            'shops' => $user->tenants()->select(['uuid', 'shop_name', 'status'])->orderBy('id')->get(),
        ]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('central.pages.users.edit', [
            'user' => $user->load('country'),
            'countries' => Country::where(fn ($query) => $query->where('is_active', true)->orWhere('id', $user->country_id))
                ->orderBy('name_fr')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UserManager $users): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $result = $users->update($actor, $user, $request->toDto());
        $destination = Gate::forUser($actor)->allows('view', $user) ? 'central.users.show' : 'dashboard';
        $response = redirect()->route($destination, $destination === 'central.users.show' ? [$user] : [])
            ->with('status', 'Informations du compte enregistrées.');
        if ($result->emailSent === false) {
            return $response->with('warning', 'Les informations sont enregistrées, mais l’e-mail n’a pas pu être envoyé. Vérifiez le SMTP puis renvoyez l’invitation.');
        }

        return $response;
    }

    public function resendInvitation(Request $request, User $user, UserManager $users): RedirectResponse
    {
        Gate::authorize('update', $user);
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $result = $users->resendInvitation($actor, $user);
        $destination = Gate::forUser($actor)->allows('view', $user) ? 'central.users.show' : 'dashboard';
        $response = redirect()->route($destination, $destination === 'central.users.show' ? [$user] : []);

        return $result->emailSent === true
            ? $response->with('status', 'L’invitation a été envoyée.')
            : $response->with('warning', 'L’invitation n’a pas pu être envoyée. Vérifiez la configuration SMTP.');
    }
}
