<?php

namespace App\Http\Controllers\Central\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\LegalVerificationRequest;
use App\Models\Central\User;
use App\Services\Central\Users\UserApprovalService;
use App\Enums\Central\Users\StatusEnum;
use Illuminate\Http\RedirectResponse;

class LegalVerificationController extends Controller
{
    public function validateProfile(LegalVerificationRequest $request, User $user, UserApprovalService $approvals): RedirectResponse
    {
        return $this->decide($request, $user, $approvals, true);
    }

    public function rejectProfile(LegalVerificationRequest $request, User $user, UserApprovalService $approvals): RedirectResponse
    {
        return $this->decide($request, $user, $approvals, false);
    }

    private function decide(LegalVerificationRequest $request, User $user, UserApprovalService $approvals, bool $validated): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $approvals->decide($actor, $user, $validated, $request->validated('reason'),
            (int) $request->validated('legal_profile_version'), StatusEnum::from((int) $request->validated('expected_status')));

        return redirect()->route('central.users.show', $user)
            ->with('status', $validated ? 'Compte approuvé et dossier validé.' : 'Compte refusé et dossier rejeté.');
    }
}
