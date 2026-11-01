<?php

namespace App\Http\Requests\Central\Shipping;

use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShippingCarrierActionRequest extends FormRequest
{
    /** Actions du contrôleur gérées ici ; le nom de l'action est aussi celui de l'ability de la policy. */
    private const array ACTIONS = ['activate', 'archive'];

    private const array FORBIDDEN_FIELDS = [
        'code', 'name', 'id', 'uuid', 'adapter', 'default_api_url',
        'reference_source', 'reference_version', 'synced_at', 'deleted_at',
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $carrier = $this->route('carrier');
        $action = $this->route()?->getActionMethod();

        return $actor instanceof User
            && $carrier instanceof ShippingCarrier
            && in_array($action, self::ACTIONS, true)
            && Gate::forUser($actor)->allows($action, $carrier);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:'.ShippingCarrier::STATE_PATTERN],
            'is_active' => $this->route()?->getActionMethod() === 'activate' ? ['required', 'boolean'] : ['prohibited'],
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }
}
