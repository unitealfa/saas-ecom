<?php

namespace App\Http\Requests\Central\Tenants;

use App\Enums\Central\Tenants\StatusEnum;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChangeTenantStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');
        $ability = $this->route()->getActionMethod();

        return $tenant instanceof Tenant && in_array($ability, ['suspend', 'activate', 'archive'], true)
            && Gate::forUser($this->user('central'))->allows($ability, $tenant);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['expected_status' => ['required', Rule::enum(StatusEnum::class)], 'reason' => ['nullable', 'string', 'max:1000']];
    }
}
