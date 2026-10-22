<?php

namespace App\Http\Requests\Central\Permissions;

use App\Models\Central\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SearchPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('viewAny', Permission::class);
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');
        if (is_string($search)) {
            $this->merge(['search' => trim($search)]);
        }
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1']];
    }
}
