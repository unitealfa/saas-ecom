<?php

namespace App\Http\Controllers\Central\Admin\Users;

use App\Enums\Central\Users\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\ChangeUserStatusRequest;
use App\Http\Requests\Central\Users\StoreUserRequest;
use App\Http\Requests\Central\Users\UpdateUserRequest;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Countries\CountryCatalog;
use App\Services\Central\Users\UserManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(
        private UserManagementService $userServices,
        private CountryCatalog $countries,
    ) {}

    public function create(): View
    {
        $actor = $this->centralActor();
        $canCreateMerchant = Gate::forUser($actor)->allows('create', User::class);
        $canCreateStaff = Gate::forUser($actor)->allows('createStaff', User::class);
        abort_unless($canCreateMerchant || $canCreateStaff, 403);

        return view('central.pages.admin.users.create', [
            'countries' => $this->countries->active(),
            'roles' => $this->userServices->availableRoles($actor),
            'canCreateMerchant' => $canCreateMerchant,
            'canCreateStaff' => $canCreateStaff,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $actor = $this->centralActor();
        $result = $this->userServices->create($actor, $request->toDto());
        $name = $result->user->name;

        $redirect = redirect()->route(
            Gate::forUser($actor)->allows('viewAny', User::class) ? 'central.users.index' : 'dashboard'
        );

        return $result->emailSent === true
            ? $redirect->with('status', 'Compte créé pour '.$name.'. L’invitation a été envoyée.')
            : $redirect->with('warning', 'Le compte de '.$name.' est créé, mais l’invitation n’a pas pu être envoyée. Vérifiez le SMTP puis renvoyez l’invitation depuis la fiche du compte.');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);

        return view('central.pages.admin.users.show', [
            'user' => $user->load(['country', 'phoneCountry', 'roles', 'legalVerifier']),
            'subscriptions' => $user->subscriptions()->with('plan')->orderByDesc('id')->paginate(10),
            'shops' => $user->tenants()->select(['uuid', 'shop_name', 'status'])->orderBy('id')->get(),
        ]);
    }

    public function edit(User $user, CentralUserAccess $access): View
    {
        Gate::authorize('update', $user);
        $user->load(['country', 'phoneCountry', 'roles']);

        $ownerShops = $access->isStaff($user)
            ? collect()
            : $user->tenants()->orderBy('activation_priority')->orderBy('id')->get();

        if ($ownerShops->isNotEmpty() && Gate::denies('updatePriorities', $ownerShops->first())) {
            $ownerShops = collect();
        }

        return view('central.pages.admin.users.edit', [
            'user' => $user,
            'ownerShops' => $ownerShops,
            'phoneCountries' => $this->countries->forPhone($user->phone_country_id),
            'countries' => $this->countries->forProfile($user->country_id),
        ]);
    }

    public function history(User $user): View
    {
        return view('central.pages.admin.users.history', [
            'user' => $user,
            'history' => $this->userServices->history($this->centralActor(), $user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $result = $this->userServices->update($this->centralActor(), $user, $request->toDto());
        $redirect = $this->afterAction($user);

        return $result->emailSent === false
            ? $redirect->with('warning', 'Les informations sont enregistrées, mais l’e-mail n’a pas pu être envoyé. Vérifiez le SMTP puis renvoyez l’invitation.')
            : $redirect->with('status', 'Informations du compte enregistrées.');
    }

    public function resendInvitation(User $user): RedirectResponse
    {
        Gate::authorize('resendInvitation', $user);

        $result = $this->userServices->resendInvitation($this->centralActor(), $user);
        $redirect = $this->afterAction($user);

        return $result->emailSent === true
            ? $redirect->with('status', 'L’invitation a été envoyée.')
            : $redirect->with('warning', 'L’invitation n’a pas pu être envoyée. Vérifiez la configuration SMTP.');
    }

    public function changeStatus(ChangeUserStatusRequest $request, User $user): RedirectResponse
    {
        $status = StatusEnum::from((int) $request->validated('status'));
        $this->userServices->changeStatus($this->centralActor(), $user, $status);

        $message = match (true) {
            $status === StatusEnum::SUSPENDED => 'Compte suspendu.',
            $user->refresh()->status === StatusEnum::PENDING => 'Compte remis en attente : son dossier nécessite une nouvelle approbation.',
            default => 'Compte réactivé.',
        };

        return $this->afterAction($user)->with('status', $message);
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->userServices->archive($this->centralActor(), $user);

        return redirect()->route(Gate::allows('viewAny', User::class) ? 'central.users.index' : 'dashboard')
            ->with('status', 'Compte archivé. Les données historiques et les boutiques sont conservées.');
    }

    /** Fiche du compte si l'acteur peut la voir, sinon tableau de bord. */
    private function afterAction(User $user): RedirectResponse
    {
        return Gate::forUser($this->centralActor())->allows('view', $user)
            ? redirect()->route('central.users.show', $user)
            : redirect()->route('dashboard');
    }
}
