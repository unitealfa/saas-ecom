<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Plans\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SearchPlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('viewAny', Plan::class);
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
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
