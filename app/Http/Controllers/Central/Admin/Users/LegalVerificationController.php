<?php

namespace App\Http\Controllers\Central\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\LegalVerificationRequest;
use App\Models\Central\User;
use App\Services\Central\Users\UserApprovalService;
use Illuminate\Http\RedirectResponse;

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

    private function decide(LegalVerificationRequest $request, User $user, bool $validated): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $this->approvals->decide($actor, $user, $validated, $request->validated('reason'),
            $request->profileVersion(), $request->expectedStatus());

        return redirect()->route('central.users.show', $user)
            ->with('status', $validated ? 'Compte approuvé et dossier validé.' : 'Compte refusé et dossier rejeté.');
    }
}
