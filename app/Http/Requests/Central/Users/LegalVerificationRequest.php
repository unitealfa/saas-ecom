<?php

namespace App\Http\Requests\Central\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class LegalVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows(
            $this->route()->getActionMethod() === 'validateProfile' ? 'validateLegal' : 'rejectLegal',
            $this->route('user'),
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'legal_profile_version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
