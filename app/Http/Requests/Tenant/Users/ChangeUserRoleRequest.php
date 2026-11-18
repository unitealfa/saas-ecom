<?php

namespace App\Http\Requests\Tenant\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ChangeUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('tenant'))->allows('manageRoles', $this->route('member'));
    }
    public function rules(): array
    {
        return ['action' => ['required', 'in:add,remove'], 'role' => ['required', 'uuid'],
            'expected_state' => ['required', 'string', 'size:64']];
    }
}
