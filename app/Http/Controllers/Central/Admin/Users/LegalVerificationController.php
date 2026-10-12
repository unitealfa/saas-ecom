<?php

namespace App\Http\Controllers\Central\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\LegalVerificationRequest;
use App\Models\Central\User;
use App\Services\Central\Users\LegalVerificationService;
use Illuminate\Http\RedirectResponse;

class LegalVerificationController extends Controller
{
    public function validateProfile(LegalVerificationRequest $request, User $user, LegalVerificationService $legal): RedirectResponse
    {
        return $this->decide($request, $user, $legal, true);
    }

    public function rejectProfile(LegalVerificationRequest $request, User $user, LegalVerificationService $legal): RedirectResponse
    {
        return $this->decide($request, $user, $legal, false);
    }

    private function decide(LegalVerificationRequest $request, User $user, LegalVerificationService $legal, bool $validated): RedirectResponse
    {
        $actor = $request->user('central');
        abort_unless($actor instanceof User, 403);
        $legal->decide($actor, $user, (int) $request->validated('legal_profile_version'), $validated, $request->validated('reason'));

        return redirect()->route('central.users.show', $user)
            ->with('status', $validated ? 'Dossier professionnel validé.' : 'Vérification professionnelle rejetée.');
    }
}
