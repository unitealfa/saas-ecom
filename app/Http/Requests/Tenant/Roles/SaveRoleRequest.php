<?php

namespace App\Http\Requests\Tenant\Roles;

use App\Models\Tenant\Roles\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class SaveRoleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $selected = [];
        foreach ((array) $this->input('permissions', []) as $uuid => $days) {
            if ($days !== '' && $days !== null) {
                $selected[strtolower((string) $uuid)] = $days;
            }
        }
        $this->merge(['permissions' => $selected]);
    }

    public function authorize(): bool
    {
        $role = $this->route('role');
        return Gate::forUser($this->user('tenant'))->allows($role instanceof Role ? 'update' : 'create', $role ?? Role::class);
    }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[a-z][a-z0-9-]*$/'],
            'label' => ['required', 'string', 'min:2', 'max:150'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'integer', 'min:1', 'max:9999'],
            'expected_state' => ['required_if:_method,PUT', 'nullable', 'string', 'size:64'],
        ];
    }
}
