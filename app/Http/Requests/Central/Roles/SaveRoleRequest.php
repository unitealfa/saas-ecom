<?php

namespace App\Http\Requests\Central\Roles;

use App\DTOs\Central\Roles\RoleData;
use App\Models\Central\Role;
use App\Services\Central\Roles\CentralRoleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return Gate::forUser($this->user('central'))->allows($role === null ? 'create' : 'edit', $role ?? Role::class);
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $label = $this->input('label');

        if ($this->has('name')) {
            $this->merge(['name' => is_string($name) ? trim($name) : $name]);
        }
        if ($this->has('label')) {
            $this->merge(['label' => is_string($label) ? trim($label) : $label]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $presence = $this->route('role') === null ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'required', 'string', 'max:255'], 'label' => [$presence, 'required', 'string', 'max:255'],
            'permissions' => [$presence, 'required', 'array', 'min:1'],
            'permissions.*' => ['uuid', 'distinct', Rule::exists('permissions', 'uuid')->where('guard_name', 'central')
                ->whereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS))],
            'durations' => ['sometimes', 'array'], 'durations.*' => ['required', 'integer', 'between:1,9999'],
            'is_super_admin' => ['prohibited'], 'is_protected' => ['prohibited'], 'is_system' => ['prohibited'],
            'guard_name' => ['prohibited'], 'uuid' => ['prohibited'], 'id' => ['prohibited'],
            'permission_signature' => ['prohibited'], 'permission_version' => ['prohibited'], 'assigned_at' => ['prohibited'],
        ];
    }

    public function toDto(): RoleData
    {
        $data = $this->validated();
        $grants = null;
        if (isset($data['permissions'])) {
            $grants = [];
            foreach ($data['permissions'] as $uuid) {
                $grants[$uuid] = isset($data['durations'][$uuid]) ? (int) $data['durations'][$uuid] : null;
            }
        }

        return new RoleData($data['name'] ?? null, $data['label'] ?? null, $grants,
            $grants === null ? array_map(intval(...), $data['durations'] ?? []) : []);
    }
}
