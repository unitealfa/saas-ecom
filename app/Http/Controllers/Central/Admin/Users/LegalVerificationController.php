<?php

namespace App\Http\Controllers\Central\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\LegalVerificationRequest;
use App\Models\Central\Users\User;
use App\Services\Central\Users\UserApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LegalVerificationController extends Controller
{
    public function __construct(private UserApprovalService $approvals) {}

    public function validateProfile(LegalVerificationRequest $request, User $user): RedirectResponse
    {
        return $this->decide($request, $user, true);
    }

    public function rejectProfile(LegalVerificationRequest $request, User $user): RedirectResponse
    {
        return $this->decide($request, $user, false);
    }

    private function decide(LegalVerificationRequest $request, User $user, bool $approved): RedirectResponse
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

        $redirect = Gate::forUser($this->centralActor())->allows('view', $user)
            ? redirect()->route('central.users.show', $user)
            : redirect()->route('dashboard');

        return $redirect->with(
            'status',
            $approved ? 'Compte approuvé et dossier validé.' : 'Compte refusé et dossier rejeté.',
        );
    }
}
