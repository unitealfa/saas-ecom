<?php

namespace App\Http\Requests\Central;

use App\DTOs\Central\RoleData;
use App\Models\Central\Role;
use App\Services\Central\CentralRoleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return Gate::forUser($this->user('central'))->allows($role === null ? 'create' : 'update', $role ?? Role::class);
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $label = $this->input('label');

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'label' => is_string($label) ? trim($label) : $label,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'label' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['uuid', 'distinct', Rule::exists('permissions', 'uuid')->where('guard_name', 'central')
                ->whereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS))],
            'durations' => ['sometimes', 'array'], 'durations.*' => ['required', 'integer', 'between:1,9999'],
            'is_super_admin' => ['prohibited'], 'is_protected' => ['prohibited'], 'is_system' => ['prohibited'],
        ];
    }

    public function toDto(): RoleData
    {
        $data = $this->validated();
        $durations = [];
        foreach ($data['permissions'] as $uuid) {
            $durations[$uuid] = (int) ($data['durations'][$uuid] ?? 9999);
        }

        return new RoleData($data['name'], $data['label'], $durations);
    }
}
