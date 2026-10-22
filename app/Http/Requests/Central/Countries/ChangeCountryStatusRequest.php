<?php

namespace App\Http\Requests\Central\Countries;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ChangeCountryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('activate', $this->route('country'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
