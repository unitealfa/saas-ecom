<?php

namespace App\Http\Requests\Central\Shipping;

use App\Models\Central\Shipping\PickupPoint;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PickupPointActionRequest extends FormRequest
{
    /** Actions du contrôleur gérées ici ; le nom de l'action est aussi celui de l'ability de la policy. */
    private const array ACTIONS = ['activate', 'archive'];

    private const array FORBIDDEN_FIELDS = [
        'id', 'uuid', 'carrier_id', 'province_id', 'municipality_id', 'reference_version',
        'carrier_uuid', 'province_uuid', 'municipality_uuid', 'external_code', 'synced_at', 'deleted_at',
        'province_type', 'municipality_type', ...PickupPoint::EDITABLE,
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $point = $this->route('point');
        $action = $this->route()?->getActionMethod();

        return $actor instanceof User
            && $point instanceof PickupPoint
            && in_array($action, self::ACTIONS, true)
            && Gate::forUser($actor)->allows($action, $point);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:'.PickupPoint::STATE_PATTERN],
            'is_carrier_active' => $this->route()?->getActionMethod() === 'activate' ? ['required', 'boolean'] : ['prohibited'],
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }
}
