<?php

namespace App\Http\Requests\Central\Permissions;

use App\Models\Central\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('permission');

        return $permission instanceof Permission && Gate::forUser($this->user('central'))->allows('update', $permission);
    }

    protected function prepareForValidation(): void
    {
        $label = $this->input('label');
        if (is_string($label)) {
            $this->merge(['label' => trim($label)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'name' => ['prohibited'], 'guard_name' => ['prohibited'], 'feature_code' => ['prohibited'],
            'id' => ['prohibited'], 'uuid' => ['prohibited'], 'roles' => ['prohibited'],
            'permissions' => ['prohibited'], 'durations' => ['prohibited'],
        ];
    }
}
