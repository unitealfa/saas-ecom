<?php

namespace App\Http\Requests\Central\Geography;

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SearchGeographyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows('viewAny', GeographicArea::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'country_uuid' => ['nullable', 'uuid'],
            'parent_uuid' => ['nullable', 'uuid'],
            'type' => ['nullable', Rule::enum(GeoZoneTypeEnum::class)],
            'state' => ['nullable', Rule::in(['all', 'active', 'inactive', 'archived'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}