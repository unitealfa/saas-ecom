<?php

namespace App\Http\Requests\Central\Tenants;

use App\Models\Central\Tenants\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTenantPrioritiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant && Gate::forUser($this->user('central'))->allows('updatePriorities', $tenant);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'priorities' => ['required', 'array', 'min:1'],
            'priorities.*' => ['required', 'integer', 'min:1', 'max:2147483647', 'distinct'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
