<?php

namespace App\Http\Requests\Tenant\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ChangeMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('tenant'))->allows('manageMembership', $this->route('member'));
    }
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:update,suspend,archive'],
            'last_name' => ['required_if:action,update', 'nullable', 'string', 'max:150'],
            'first_name' => ['nullable', 'string', 'max:150'],
            'expected_state' => ['required', 'string', 'size:64'],
        ];
    }
}
