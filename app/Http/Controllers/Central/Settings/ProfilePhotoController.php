<?php

namespace App\Http\Controllers\Central\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\UpdateProfilePhotoRequest;
use App\Models\Central\User;
use App\Services\Central\Users\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function __construct(private ProfilePhotoService $photos) {}

    public function show(Request $request, User $user): BinaryFileResponse|StreamedResponse
    {
        return $this->photos->show($this->actor($request), $user);
    }

    public function update(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $this->photos->update($this->actor($request), $request->file('photo'));

        return redirect()->route('profile.edit')->with('photo_status', 'Photo de profil enregistrée.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->photos->remove($this->actor($request));

        return redirect()->route('profile.edit')->with('photo_status', 'L’avatar par défaut est maintenant utilisé.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user('central');
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
