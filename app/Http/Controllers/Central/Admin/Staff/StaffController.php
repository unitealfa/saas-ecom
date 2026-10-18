<?php

namespace App\Http\Controllers\Central\Admin\Staff;

use App\Enums\Central\Users\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\ChangeStaffStatusRequest;
use App\Http\Requests\Central\Users\StoreStaffRequest;
use App\Http\Requests\Central\Users\UpdateStaffRequest;
use App\Models\Central\Country;
use App\Models\Central\User;
use App\Services\Central\Users\StaffInvitationService;
use App\Services\Central\Users\StaffManagementService;
use App\Services\Central\Users\UserManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StaffController extends Controller
{
    public function create(Request $request, UserManagementService $users): View
    {
        $actor = $this->actor($request);
        Gate::forUser($actor)->authorize('createStaff', User::class);

        return view('central.pages.admin.staff.create', [
            'countries' => Country::where('is_active', true)->orderBy('name_fr')->get(),
            'roles' => $users->availableRoles($actor),
        ]);
    }

    public function store(StoreStaffRequest $request, StaffManagementService $staff): RedirectResponse
    {
        $result = $staff->create($this->actor($request), $request->toDto());
        $response = redirect()->route('central.staff.index')->with('status', 'Compte personnel créé pour '.$result->user->name.'.');

        return $result->emailSent === true
            ? $response->with('status', 'Compte personnel créé. L’invitation a été envoyée.')
            : $response->with('warning', 'Le compte est créé, mais l’invitation n’a pas pu être envoyée. Vérifiez le SMTP puis renvoyez l’invitation.');
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
            'phoneCountries' => Country::withTrashed()->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')
                ->orWhere('id', $user->phone_country_id))->orderBy('name_fr')->get(),
            'countries' => Country::where(fn ($query) => $query->where('is_active', true)->orWhere('id', $user->country_id))
                ->orderBy('name_fr')->get(),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $user, StaffManagementService $staff): RedirectResponse
    {
        $result = $staff->update($this->actor($request), $user, $request->toDto());
        $response = redirect()->route('central.staff.show', $user)->with('status', 'Informations du personnel enregistrées.');

        return $result->emailSent === false
            ? $response->with('warning', 'Les informations sont enregistrées, mais l’e-mail n’a pas pu être envoyé. Vérifiez le SMTP.')
            : $response;
    }

    public function resendInvitation(Request $request, User $user, StaffInvitationService $invitations): RedirectResponse
    {
        $result = $invitations->resend($this->actor($request), $user);
        $response = redirect()->route('central.staff.show', $user);

        return $result->emailSent === true
            ? $response->with('status', 'L’invitation a été envoyée.')
            : $response->with('warning', 'L’invitation n’a pas pu être envoyée. Vérifiez le SMTP.');
    }

    public function changeStatus(ChangeStaffStatusRequest $request, User $user, StaffManagementService $staff): RedirectResponse
    {
        $status = StatusEnum::from((int) $request->validated('status'));
        $staff->changeStatus($this->actor($request), $user, $status);

        return redirect()->route('central.staff.show', $user)
            ->with('status', $status === StatusEnum::SUSPENDED ? 'Administrateur suspendu.' : 'Administrateur réactivé.');
    }

    public function destroy(Request $request, User $user, StaffManagementService $staff): RedirectResponse
    {
        $staff->archive($this->actor($request), $user);

        return redirect()->route('central.staff.index')->with('status', 'Administrateur archivé. Les données historiques sont conservées.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
