<?php

namespace App\Http\Requests\Central\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateStaffPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('updateStaffPermissions', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'size:64'],
            'grants' => ['present', 'array'],
            'grants.*' => ['array:permission,enabled,days,renew'],
            'grants.*.permission' => ['required', 'uuid', 'distinct', Rule::exists('permissions', 'uuid')->where('guard_name', 'central')],
            'grants.*.enabled' => ['required', 'boolean'],
            'grants.*.days' => ['required', 'integer', 'between:1,9999'],
            'grants.*.renew' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
