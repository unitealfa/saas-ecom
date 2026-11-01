<?php

namespace App\Http\Requests\Central\Shipping;

use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CarrierGeoMappingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $mapping = $this->route('mapping');

        return $actor instanceof User && $mapping instanceof CarrierGeoMapping
            && Gate::forUser($actor)->allows('archive', $mapping);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:'.CarrierGeoMapping::STATE_PATTERN],
            ...array_fill_keys([...CarrierGeoMapping::EDITABLE, 'carrier_uuid', 'geographic_area_uuid',
                'id', 'uuid', 'carrier_id', 'geographic_area_id', 'is_active', 'mapping_version', 'zone_type', 'verified_at', 'synced_at'], ['prohibited'])];
    }
}
