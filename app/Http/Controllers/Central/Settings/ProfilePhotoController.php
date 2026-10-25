<?php

namespace App\Http\Controllers\Central\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Users\UpdateProfilePhotoRequest;
use App\Models\Central\User;
use App\Services\Central\Users\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function __construct(private ProfilePhotoService $photos) {}

    public function show(User $user): BinaryFileResponse|StreamedResponse
    {
        return $this->photos->show($this->centralActor(), $user);
    }

    public function update(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $this->photos->update($this->centralActor(), $request->file('photo'));

        return redirect()->route('profile.edit')->with('photo_status', 'Photo de profil enregistrée.');
    }

    public function destroy(): RedirectResponse
    {
        $this->photos->remove($this->centralActor());

        return redirect()->route('profile.edit')->with('photo_status', 'L’avatar par défaut est maintenant utilisé.');
    }
}