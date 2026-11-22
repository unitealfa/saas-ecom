<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\AcceptTeamInvitationRequest;
use App\Models\Tenant\Users\TeamInvitation;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;
use App\Services\Tenant\Shop\ShopProfileService;
use App\Services\Tenant\Users\TeamInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AcceptTeamInvitationController extends Controller
{
    public function __construct(private readonly TeamInvitationService $invitations) {}

    public function show(Request $request, TeamInvitation $invitation, ShopProfileService $profiles): Response
    {
        $secret = $request->query('token');
        if (! is_string($secret) || ! $this->invitations->valid($invitation, $secret)) {
            return response()->view('tenant.pages.auth.invitation-unavailable');
        }

        $member = User::withTrashed()->where('email', $invitation->email)->first();
        if ($member !== null && (! TenantAccess::editableEmployee($member) || ! TenantAccess::active($member))) {
            return response()->view('tenant.pages.auth.invitation-unavailable');
        }

        $actor = $request->user('tenant');
        $view = $member === null && $actor === null && $request->boolean('compte')
            ? 'tenant.pages.auth.create-invited-account'
            : 'tenant.pages.auth.accept-invitation';

        return response()->view($view, [
            'invitation' => $invitation,
            'token' => $secret,
            'shopName' => $profiles->shop()->shop_name,
            'hasAccount' => $member !== null,
            'switchAccount' => $actor instanceof User && ($member === null || ! $actor->is($member)),
        ])->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'private, no-store']);
    }

    public function switchAccount(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $secret = $request->input('token');
        abort_unless(is_string($secret) && $this->invitations->valid($invitation, $secret), 403);

        Auth::guard('tenant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.invitations.show', ['invitation' => $invitation, 'token' => $secret, 'compte' => 1])
            ->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'private, no-store']);
    }

    public function accept(AcceptTeamInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $data = $request->validated();
        $member = $this->invitations->accept($invitation, $data['token'], $data['password'] ?? null,
            $data['last_name'] ?? null, $data['first_name'] ?? null);
        if ($member->wasRecentlyCreated) {
            Auth::guard('tenant')->login($member);
            $request->session()->regenerate(true);
        }

        return to_route('tenant.dashboard')->with('status', 'Invitation acceptée.');
    }
}
