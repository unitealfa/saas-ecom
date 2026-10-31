<?php

namespace App\Http\Requests\Central\Geography;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreGeographicAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $area = $this->route('area');

        return $actor instanceof User && Gate::forUser($actor)->allows($area instanceof GeographicArea ? 'update' : 'create', $area ?? GeographicArea::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $editing = $this->route('area') instanceof GeographicArea;

        return [
            'country_uuid' => [$editing ? 'prohibited' : 'required', 'uuid'],
            'type' => [$editing ? 'prohibited' : 'required', Rule::enum(GeoZoneTypeEnum::class)],
            'parent_uuid' => [$editing ? 'prohibited' : 'nullable', 'uuid'],
            'code' => [$editing ? 'prohibited' : 'required', 'string', 'max:255'],
            'name_fr' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'reference_source' => ['nullable', 'string', 'max:255'],
            'reference_version' => ['nullable', 'string', 'max:255'],
            'effective_at' => ['nullable', 'date_format:Y-m-d'],
            'is_active' => ['prohibited'],
            'expected_state' => [$editing ? 'required' : 'prohibited', 'string', 'size:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['code', 'name_fr', 'name_ar', 'reference_source', 'reference_version'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }
        $this->merge($values);
    }
}
