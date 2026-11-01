<?php

namespace App\Http\Requests\Central\Shipping;

use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShippingCarrierActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $carrier = $this->route('carrier');
        $ability = $this->route()?->getActionMethod();

        return $actor instanceof User && $carrier instanceof ShippingCarrier
            && in_array($ability, ['activate', 'archive'], true)
            && Gate::forUser($actor)->allows($ability, $carrier);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:'.ShippingCarrier::STATE_PATTERN],
            'is_active' => $this->route()?->getActionMethod() === 'activate' ? ['required', 'boolean'] : ['prohibited'],
            ...array_fill_keys(['code', 'name', 'id', 'uuid', 'adapter', 'default_api_url',
                'reference_source', 'reference_version', 'synced_at', 'deleted_at'], ['prohibited']),
        ];
    }
}
