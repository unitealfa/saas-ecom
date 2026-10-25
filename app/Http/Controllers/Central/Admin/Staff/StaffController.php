<?php

namespace App\Http\Controllers\Central\Admin\Staff;

use App\Enums\Central\Users\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\ChangeStaffStatusRequest;
use App\Http\Requests\Central\Users\StoreStaffRequest;
use App\Http\Requests\Central\Users\UpdateStaffRequest;
use App\Models\Central\User;
use App\Services\Central\Countries\CountryCatalog;
use App\Services\Central\Users\StaffInvitationService;
use App\Services\Central\Users\StaffManagementService;
use App\Services\Central\Users\UserManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class StaffController extends Controller
{
    public function __construct(private CountryCatalog $countries) {}

    public function create(UserManagementService $users): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('createStaff', User::class);

        return view('central.pages.admin.staff.create', [
            'countries' => $this->countries->active(),
            'roles' => $users->availableRoles($actor),
        ]);
    }

    public function store(StoreStaffRequest $request, StaffManagementService $staff): RedirectResponse
    {
        $result = $staff->create($this->centralActor(), $request->toDto());
        $redirect = redirect()->route('central.staff.index');

        return $result->emailSent === true
            ? $redirect->with('status', 'Compte personnel créé pour '.$result->user->name.'. L’invitation a été envoyée.')
            : $redirect->with('warning', 'Le compte de '.$result->user->name.' est créé, mais l’invitation n’a pas pu être envoyée. Vérifiez le SMTP puis renvoyez l’invitation.');
    }

    public function show(User $user): View
    {
        Gate::authorize('viewStaff', $user);

        return view('central.pages.admin.staff.show', ['user' => $user->load(['country', 'phoneCountry', 'roles'])]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('updateStaff', $user);

        return view('central.pages.admin.staff.edit', [
            'user' => $user->load(['country', 'phoneCountry']),
            'phoneCountries' => $this->countries->forPhone($user->phone_country_id),
            'countries' => $this->countries->forProfile($user->country_id),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $user, StaffManagementService $staff): RedirectResponse
    {
        $result = $staff->update($this->centralActor(), $user, $request->toDto());
        $redirect = redirect()->route('central.staff.show', $user)
            ->with('status', 'Informations du personnel enregistrées.');

        return $result->emailSent === false
            ? $redirect->with('warning', 'Les informations sont enregistrées, mais l’e-mail n’a pas pu être envoyé. Vérifiez le SMTP.')
            : $redirect;
    }

    public function resendInvitation(User $user, StaffInvitationService $invitations): RedirectResponse
    {
        $result = $invitations->resend($this->centralActor(), $user);
        $redirect = redirect()->route('central.staff.show', $user);

        return $result->emailSent === true
            ? $redirect->with('status', 'L’invitation a été envoyée.')
            : $redirect->with('warning', 'L’invitation n’a pas pu être envoyée. Vérifiez le SMTP.');
    }

    public function changeStatus(ChangeStaffStatusRequest $request, User $user, StaffManagementService $staff): RedirectResponse
    {
        $status = StatusEnum::from((int) $request->validated('status'));
        $staff->changeStatus($this->centralActor(), $user, $status);

        return redirect()->route('central.staff.show', $user)->with('status', match ($status) {
            StatusEnum::SUSPENDED => 'Administrateur suspendu.',
            default => 'Administrateur réactivé.',
        });
    }

    public function destroy(User $user, StaffManagementService $staff): RedirectResponse
    {
        $staff->archive($this->centralActor(), $user);

        return redirect()->route('central.staff.index')
            ->with('status', 'Administrateur archivé. Les données historiques sont conservées.');
    }
}