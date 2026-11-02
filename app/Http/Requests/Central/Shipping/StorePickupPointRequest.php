<?php

namespace App\Http\Requests\Central\Shipping;

use App\Models\Central\Shipping\PickupPoint;
use App\Models\Central\Users\User;
use App\Services\Central\Shipping\PickupPointService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePickupPointRequest extends FormRequest
{
    private const array FORBIDDEN = [
        'id', 'uuid', 'carrier_id', 'province_id', 'municipality_id', 'province_type', 'municipality_type',
        'reference_version', 'is_carrier_active', 'synced_at', 'deleted_at',
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $point = $this->point();

        return $actor instanceof User
            && Gate::forUser($actor)->allows($point ? 'update' : 'create', $point ?? PickupPoint::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...PickupPointService::dataRules(),
            'expected_state' => $this->point()
                ? ['required', 'string', 'regex:'.PickupPoint::STATE_PATTERN]
                : ['prohibited'],
            ...array_fill_keys(self::FORBIDDEN, ['prohibited']),
        ];
    }

    private function point(): ?PickupPoint
    {
        $point = $this->route('point');

        return $point instanceof PickupPoint ? $point : null;
    }
}
