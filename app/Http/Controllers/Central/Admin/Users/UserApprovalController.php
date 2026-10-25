<?php

namespace App\Http\Controllers\Central\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\UserApprovalRequest;
use App\Models\Central\User;
use App\Services\Central\Users\UserApprovalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UserApprovalController extends Controller
{
    public function __construct(private UserApprovalService $approvals) {}

    public function index(): View
    {
        return view('central.pages.admin.users.pending', [
            'users' => $this->approvals->pending($this->centralActor()),
        ]);
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
        Gate::forUser($this->centralActor())->authorize($approved ? 'approve' : 'reject', $user);

        $this->approvals->decide(
            $this->centralActor(),
            $user,
            $approved,
            $request->validated('reason'),
            $request->profileVersion(),
            $request->expectedStatus(),
        );

        return $this->afterDecision($user)->with(
            'status',
            $approved
                ? 'Compte approuvé et dossier validé. L’e-mail doit être vérifié séparément.'
                : 'Compte refusé et dossier rejeté.',
        );
    }

    private function afterDecision(User $user): RedirectResponse
    {
        if (! Gate::forUser($this->centralActor())->allows('viewAny', User::class)) {
            return redirect()->route('dashboard');
        }

        $previous = url()->previous();
        $allowed = [route('central.users.index'), route('central.users.pending'), route('central.users.show', $user)];

        return redirect()->to(in_array(explode('?', $previous, 2)[0], $allowed, true)
            ? $previous : route('central.users.index'));
    }
}
