<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\AcceptTeamInvitationRequest;
use App\Models\Tenant\Users\TeamInvitation;
use App\Services\Tenant\Users\TeamInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AcceptTeamInvitationController extends Controller
{
    public function __construct(private readonly TeamInvitationService $invitations) {}
    public function show(Request $request, TeamInvitation $invitation): View
    {
        $secret = (string) $request->query('token', '');
        if (! $this->invitations->valid($invitation, $secret)) {
            return view('tenant.pages.auth.invitation-unavailable');
        }
        return view('tenant.pages.auth.accept-invitation', ['invitation' => $invitation, 'token' => $secret]);
    }
    public function accept(AcceptTeamInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $data = $request->validated();
        $existing = \App\Models\Tenant\Users\User::where('email', $invitation->email)->first();
        if ($existing === null && (empty($data['last_name']) || empty($data['password']))) {
            return back()->withErrors(['last_name' => 'Nom et mot de passe requis pour un nouveau compte.']);
        }
        $member = $this->invitations->accept($invitation, $data['token'], $data['password'] ?? null,
            $data['last_name'] ?? null, $data['first_name'] ?? null);
        if ($existing === null) {
            Auth::guard('tenant')->login($member);
            $request->session()->regenerate(true);
        }
        return to_route('tenant.dashboard')->with('status', 'Invitation acceptée.');
    }
}
