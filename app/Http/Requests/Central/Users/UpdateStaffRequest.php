<?php

namespace App\Http\Requests\Central\Users;

use App\DTOs\Central\Users\UserProfileData;
use Illuminate\Support\Facades\Gate;

class UpdateStaffRequest extends UpdateUserRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('updateStaff', $this->route('user'));
    }

    public function rules(): array
    {
        $rules = parent::rules();
        foreach (UserProfileData::PROFESSIONAL_FIELDS as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
