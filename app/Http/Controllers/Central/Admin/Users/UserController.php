<?php

namespace App\Http\Controllers\Central\Admin\Users;

use App\Enums\Central\Users\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\ChangeUserStatusRequest;
use App\Http\Requests\Central\Users\StoreUserRequest;
use App\Http\Requests\Central\Users\UpdateUserRequest;
use App\Models\Central\Country;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Users\UserManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(public UserManagementService $userServices) {}

    public function create(Request $request): View
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        abort_unless(Gate::forUser($actor)->allows('create', User::class)
            || Gate::forUser($actor)->allows('createStaff', User::class), 403);

        return view('central.pages.admin.users.create', [
            'countries' => Country::where('is_active', true)->orderBy('name_fr')->get(),
            'roles' => $this->userServices->availableRoles($actor),
            'canCreateMerchant' => Gate::forUser($actor)->allows('create', User::class),
            'canCreateStaff' => Gate::forUser($actor)->allows('createStaff', User::class),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $result = $this->userServices->create($actor, $request->toDto());

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

        return view('central.pages.admin.users.show', [
            'user' => $user->load(['country', 'roles', 'legalVerifier']),
            'subscriptions' => $user->subscriptions()->with('plan')->orderByDesc('id')->paginate(10),
            'shops' => $user->tenants()->select(['uuid', 'shop_name', 'status'])->orderBy('id')->get(),
        ]);
    }

    public function edit(User $user, CentralUserAccess $access): View
    {
        Gate::authorize('update', $user);
        $user->load(['country', 'roles']);
        $ownerShops = $access->isStaff($user) ? collect()
            : $user->tenants()->orderBy('activation_priority')->orderBy('id')->get();
        if ($ownerShops->isNotEmpty() && Gate::denies('updatePriorities', $ownerShops->first())) {
            $ownerShops = collect();
        }

        return view('central.pages.admin.users.edit', [
            'user' => $user,
            'ownerShops' => $ownerShops,
            'countries' => Country::where(fn ($query) => $query->where('is_active', true)->orWhere('id', $user->country_id))
                ->orderBy('name_fr')->get(),
        ]);
    }

    public function history(Request $request, User $user): View
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return view('central.pages.admin.users.history', ['user' => $user, 'history' => $this->userServices->history($actor, $user)]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $result = $this->userServices->update($actor, $user, $request->toDto());
        $destination = Gate::forUser($actor)->allows('view', $user) ? 'central.users.show' : 'dashboard';
        $response = redirect()->route($destination, $destination === 'central.users.show' ? [$user] : [])
            ->with('status', 'Informations du compte enregistrées.');
        if ($result->emailSent === false) {
            return $response->with('warning', 'Les informations sont enregistrées, mais l’e-mail n’a pas pu être envoyé. Vérifiez le SMTP puis renvoyez l’invitation.');
        }

        return $response;
    }

    public function resendInvitation(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('resendInvitation', $user);
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $result = $this->userServices->resendInvitation($actor, $user);
        $destination = Gate::forUser($actor)->allows('view', $user) ? 'central.users.show' : 'dashboard';
        $response = redirect()->route($destination, $destination === 'central.users.show' ? [$user] : []);

        return $result->emailSent === true
            ? $response->with('status', 'L’invitation a été envoyée.')
            : $response->with('warning', 'L’invitation n’a pas pu être envoyée. Vérifiez la configuration SMTP.');
    }

    public function changeStatus(ChangeUserStatusRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $status = StatusEnum::from((int) $request->validated('status'));
        $this->userServices->changeStatus($actor, $user, $status);

        return redirect()->route('central.users.show', $user)
            ->with('status', $status === StatusEnum::SUSPENDED ? 'Compte suspendu.' : 'Compte réactivé.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $this->userServices->archive($actor, $user);

        return redirect()->route('central.users.index')->with('status', 'Compte archivé. Les données historiques et les boutiques sont conservées.');
    }
}
