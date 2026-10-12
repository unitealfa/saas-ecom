<?php

namespace App\Http\Requests\Central\Countries;

use App\Models\Central\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCountryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $country = $this->route('country');

        return Gate::forUser($this->user('central'))->allows($country === null ? 'create' : 'update', $country ?? Country::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Z]{2}$/', Rule::unique(Country::class, 'code')->ignore($this->route('country')?->id)],
            'name_fr' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'is_active' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['code', 'name_fr', 'name_en', 'name_ar'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $value = $field === 'code' ? strtoupper($value) : $value;
            }
            $values[$field] = $field === 'name_ar' && $value === '' ? null : $value;
        }
        $this->merge($values);
    }
}
