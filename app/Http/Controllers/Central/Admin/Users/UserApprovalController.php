<?php

namespace App\Http\Controllers\Central\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\UserApprovalRequest;
use App\Models\Central\User;
use App\Services\Central\Users\UserApprovalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserApprovalController extends Controller
{
    public function index(Request $request, UserApprovalService $approvals): View
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return view('central.pages.admin.users.pending', ['users' => $approvals->pending($actor)]);
    }

    public function approve(UserApprovalRequest $request, User $user, UserApprovalService $approvals): RedirectResponse
    {
        return $this->decide($request, $user, $approvals, true);
    }

    public function reject(UserApprovalRequest $request, User $user, UserApprovalService $approvals): RedirectResponse
    {
        return $this->decide($request, $user, $approvals, false);
    }

    private function decide(UserApprovalRequest $request, User $user, UserApprovalService $approvals, bool $approved): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $approvals->decide($actor, $user, $approved, $request->validated('reason'));

        return redirect()->route('central.users.pending')
            ->with('status', $approved ? 'Compte approuvé. L’approbation ne remplace pas la vérification de l’e-mail.' : 'Demande d’accès refusée.');
    }
}
