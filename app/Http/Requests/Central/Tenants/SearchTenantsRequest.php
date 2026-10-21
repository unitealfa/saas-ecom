<?php

namespace App\Http\Requests\Central\Tenants;

use App\Enums\Central\Tenants\StatusEnum;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SearchTenantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->route()?->getActionMethod() === 'chooseOwner' ? 'create' : 'viewAny';

        return Gate::forUser($this->user('central'))->allows($ability, Tenant::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::enum(StatusEnum::class)],
            'page' => ['sometimes', 'integer', 'min:1']];
    }
}
