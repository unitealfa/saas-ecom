<?php

namespace App\Http\Controllers\Tenant\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Authorization\TenantAccess;
use App\Services\Tenant\Users\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProfilePhotoController extends Controller
{
    public function __construct(private ProfilePhotoService $photos) {}

    public function show(User $user): BinaryFileResponse|StreamedResponse
    {
        return $this->photos->show($this->tenantActor(), $user);
    }

    public function update(Request $request): RedirectResponse
    {
        $actor = $this->tenantActor();
        abort_unless(TenantAccess::active($actor) && TenantAccess::editableEmployee($actor), 403);
        $request->validate([
            'photo' => ['required', File::image()->rules(['mimes:jpg,jpeg,png,webp'])
                ->max(ProfilePhotoService::MAX_SIZE_KB)->dimensions(Rule::dimensions()
                ->maxWidth(ProfilePhotoService::MAX_DIMENSION)->maxHeight(ProfilePhotoService::MAX_DIMENSION))],
        ]);
        $this->photos->update($actor, $request->file('photo'));

        return redirect()->route('tenant.settings.show')->with('status', 'Photo enregistrée.');
    }

    public function destroy(): RedirectResponse
    {
        $actor = $this->tenantActor();
        abort_unless(TenantAccess::active($actor) && TenantAccess::editableEmployee($actor), 403);
        $this->photos->remove($actor);

        return redirect()->route('tenant.settings.show')->with('status', 'Photo retirée.');
    }
}
