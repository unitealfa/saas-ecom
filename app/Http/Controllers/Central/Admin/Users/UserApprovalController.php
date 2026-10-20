<?php

namespace App\Http\Controllers\Central\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\UserApprovalRequest;
use App\Models\Central\User;
use App\Enums\Central\Users\StatusEnum;
use App\Services\Central\Users\UserApprovalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserApprovalController extends Controller
{
    public function __construct(public UserApprovalService $approvals) {}
    public function index(Request $request): View
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return view('central.pages.admin.users.pending', ['users' => $this->approvals->pending($actor)]);
    }

    public function approve(UserApprovalRequest $request, User $user): RedirectResponse
    {
        return $this->decide($request, $user, $this->approvals, true);
    }

    public function reject(UserApprovalRequest $request, User $user, UserApprovalService $approvals): RedirectResponse
    {
        return $this->decide($request, $user, $approvals, false);
    }

    private function decide(UserApprovalRequest $request, User $user, UserApprovalService $approvals, bool $approved): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $approvals->decide($actor, $user, $approved, $request->validated('reason'),
            (int) $request->validated('legal_profile_version'), StatusEnum::from((int) $request->validated('expected_status')));

        return redirect()->route('central.users.pending')
            ->with('status', $approved ? 'Compte approuvé et dossier validé. L’e-mail doit être vérifié séparément.' : 'Compte refusé et dossier rejeté.');
    }
}
