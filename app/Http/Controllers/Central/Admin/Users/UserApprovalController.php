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
    public function __construct(private UserApprovalService $approvals) {}

    public function index(Request $request): View
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);

        return view('central.pages.admin.users.pending', ['users' => $this->approvals->pending($actor)]);
    }

    public function approve(UserApprovalRequest $request, User $user): RedirectResponse
    {
        return $this->decide($request, $user, true);
    }

    public function reject(UserApprovalRequest $request, User $user): RedirectResponse
    {
        return $this->decide($request, $user, false);
    }

    private function decide(UserApprovalRequest $request, User $user, bool $approved): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $this->approvals->decide($actor, $user, $approved, $request->validated('reason'),
            $request->profileVersion(), $request->expectedStatus());

        return redirect()->back(fallback: route('central.users.index'))
            ->with('status', $approved ? 'Compte approuvé et dossier validé. L’e-mail doit être vérifié séparément.' : 'Compte refusé et dossier rejeté.');
    }
}
