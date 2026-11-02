<?php

namespace App\Http\Requests\Central\Shipping;

use App\Models\Central\Shipping\CarrierGeoMapping;
use App\Models\Central\Users\User;
use App\Services\Central\Shipping\CarrierGeoMappingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCarrierGeoMappingRequest extends FormRequest
{
    private const array FORBIDDEN = [
        'id', 'uuid', 'carrier_id', 'geographic_area_id', 'zone_type',
        'mapping_version', 'is_active', 'verified_at', 'synced_at',
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $mapping = $this->mapping();

        return $actor instanceof User
            && Gate::forUser($actor)->allows($mapping ? 'update' : 'create', $mapping ?? CarrierGeoMapping::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...CarrierGeoMappingService::dataRules(),
            'expected_state' => $this->mapping()
                ? ['required', 'string', 'regex:'.CarrierGeoMapping::STATE_PATTERN]
                : ['prohibited'],
            ...array_fill_keys(self::FORBIDDEN, ['prohibited']),
        ];
    }

    private function mapping(): ?CarrierGeoMapping
    {
        $mapping = $this->route('mapping');

        return $mapping instanceof CarrierGeoMapping ? $mapping : null;
    }
}
