<?php

namespace App\Http\Requests\Central\Users;

use App\Models\Central\User;
use App\Services\Central\Users\ProfilePhotoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('central');

        return $user instanceof User && Gate::forUser($user)->allows('updateProfilePhoto', $user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photo' => ['required', File::image()->rules(['mimes:'.implode(',', array_values(ProfilePhotoService::MIME_EXTENSIONS))])
                ->max(ProfilePhotoService::MAX_SIZE_KB)->dimensions(Rule::dimensions()
                ->maxWidth(ProfilePhotoService::MAX_DIMENSION)->maxHeight(ProfilePhotoService::MAX_DIMENSION))],
        ];
    }
}
