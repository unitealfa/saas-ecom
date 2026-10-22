<?php

namespace App\Http\Requests\Central\Tenants;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant && Gate::forUser($this->user('central'))->allows('update', $tenant);
    }

    protected function prepareForValidation(): void
    {
        foreach (['shop_name', 'internal_label'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:255'],
            'internal_label' => ['required', 'string', 'max:255'],
            'expected_profile_version' => ['required', 'integer', 'min:1'],
            'slug' => ['prohibited'],
            'user_id' => ['prohibited'],
            'uuid' => ['prohibited'],
            'id' => ['prohibited'],
            'status' => ['prohibited'],
            'data' => ['prohibited'],
            'document_prefix' => ['prohibited'],
            'creation_key' => ['prohibited'],
            'creation_hash' => ['prohibited'],
            'profile_version' => ['prohibited'],
            'is_primary' => ['prohibited'],
            'activation_priority' => ['prohibited'],
            'schema_version' => ['prohibited'],
            'provisioned_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }
}
