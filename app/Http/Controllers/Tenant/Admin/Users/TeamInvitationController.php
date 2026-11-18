<?php

namespace App\Http\Controllers\Tenant\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Users\StoreTeamInvitationRequest;
use App\Models\Tenant\Roles\Role;
use App\Models\Tenant\Users\TeamInvitation;
use App\Services\Tenant\Users\TeamInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class TeamInvitationController extends Controller
{
    public function __construct(private readonly TeamInvitationService $invitations) {}
    public function index(Request $request): View
    {
        Gate::forUser($request->user('tenant'))->authorize('viewAny', TeamInvitation::class);
        return view('tenant.pages.admin.users.invitations', [
            'invitations' => TeamInvitation::with('initialRole')->latest()->paginate(20),
            'roles' => Role::orderBy('name')->get(), 'service' => $this->invitations,
        ]);
    }
    public function store(StoreTeamInvitationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = Role::where('uuid', $data['role'])->firstOrFail();
        $this->invitations->send($request->user('tenant'), $data['email'], $role);
        return back()->with('status', 'Invitation enregistrée.');
    }
    public function revoke(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $request->validate(['expected_state' => ['required', 'string', 'size:64']]);
        $this->invitations->revoke($request->user('tenant'), $invitation, $request->input('expected_state'));
        return back()->with('status', 'Invitation révoquée.');
    }
    public function resend(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $request->validate(['expected_state' => ['required', 'string', 'size:64']]);
        $this->invitations->resend($request->user('tenant'), $invitation, $request->input('expected_state'));
        return back()->with('status', 'Nouvelle invitation enregistrée.');
    }
}
