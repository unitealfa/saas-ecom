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
    private const array TRIMMED = ['code', 'name_fr', 'name_ar', 'reference_source', 'reference_version'];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $area = $this->area();

        return $actor instanceof User
            && Gate::forUser($actor)->allows($area ? 'update' : 'create', $area ?? GeographicArea::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $editing = $this->area() !== null;

        // L'identité de la zone (pays, type, parent, code) n'est saisie qu'à la création.
        $identity = [
            'country_uuid' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(GeoZoneTypeEnum::class)],
            'parent_uuid' => ['nullable', 'uuid'],
            'code' => ['required', 'string', 'max:255'],
        ];

        return [
            ...($editing ? array_fill_keys(array_keys($identity), ['prohibited']) : $identity),
            'name_fr' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'reference_source' => ['nullable', 'string', 'max:255'],
            'reference_version' => ['nullable', 'string', 'max:255'],
            'effective_at' => ['nullable', 'date_format:Y-m-d'],
            'is_active' => ['prohibited'],
            'expected_state' => $editing ? ['required', 'string', 'size:64'] : ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn (mixed $value): mixed => is_string($value) ? trim($value) : $value,
            $this->only(self::TRIMMED),
        ));
    }

    private function area(): ?GeographicArea
    {
        $area = $this->route('area');

        return $area instanceof GeographicArea ? $area : null;
    }
}