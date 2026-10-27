<?php

namespace App\Http\Requests\Central\Roles;

use App\DTOs\Central\Roles\RoleData;
use App\Models\Central\Roles\Role;
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
        foreach (['name', 'label'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }

        $permissions = $this->input('permissions');
        if (is_array($permissions)) {
            $this->merge(['permissions' => array_map(
                fn (mixed $uuid): mixed => is_string($uuid) ? strtolower($uuid) : $uuid,
                $permissions,
            )]);
        }

        $durations = $this->input('durations');
        if (is_array($durations)) {
            $this->merge(['durations' => array_change_key_case($durations)]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->route('role') === null;
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'expected_version' => [$creating ? 'prohibited' : 'required', 'integer', 'min:1'],
            'name' => [$presence, 'required', 'string', 'max:255'],
            'label' => [$presence, 'required', 'string', 'max:255'],
            'permissions' => [$presence, 'required', 'array', 'min:1'],
            'permissions.*' => [
                'uuid', 'distinct',
                Rule::exists('permissions', 'uuid')->where('guard_name', 'central')
                    ->whereIn('name', array_keys(CentralRoleCatalog::PERMISSIONS)),
            ],
            'durations' => ['sometimes', 'array'],
            'durations.*' => ['required', 'integer', 'between:1,9999'],
            'is_super_admin' => ['prohibited'],
            'is_protected' => ['prohibited'],
            'is_system' => ['prohibited'],
            'guard_name' => ['prohibited'],
            'uuid' => ['prohibited'],
            'id' => ['prohibited'],
            'permission_signature' => ['prohibited'],
            'permission_version' => ['prohibited'],
            'assigned_at' => ['prohibited'],
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

        return new RoleData(
            name: $data['name'] ?? null,
            label: $data['label'] ?? null,
            permissionDurations: $grants,
            durations: $grants === null ? array_map(intval(...), $data['durations'] ?? []) : [],
            expectedVersion: isset($data['expected_version']) ? (int) $data['expected_version'] : null,
        );
    }
}
