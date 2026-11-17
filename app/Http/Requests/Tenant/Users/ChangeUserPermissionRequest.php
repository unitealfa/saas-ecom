<?php

namespace App\Http\Requests\Tenant\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ChangeUserPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('tenant'))->allows(
            $this->input('action') === 'renew' ? 'renewPermission' : 'managePermissions', $this->route('member'));
    }
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:grant,revoke,renew'],
            'permission' => ['required', 'uuid'],
            'days' => ['required_unless:action,revoke', 'nullable', 'integer', 'min:1', 'max:9999'],
            'reason' => ['required', 'string', 'max:1000'],
            'expected_state' => ['required', 'string', 'size:64'],
        ];
    }
}
