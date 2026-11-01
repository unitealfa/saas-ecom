<?php

namespace App\Http\Requests\Central\Shipping;

use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreShippingCarrierRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtolower(trim($this->input('code')))]);
        }
    }

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $carrier = $this->route('carrier');

        return $actor instanceof User && Gate::forUser($actor)->allows(
            $carrier instanceof ShippingCarrier ? 'update' : 'create',
            $carrier instanceof ShippingCarrier ? $carrier : ShippingCarrier::class,
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $carrier = $this->route('carrier');

        return [
            'code' => $carrier instanceof ShippingCarrier
                ? ['required', 'string', Rule::in([$carrier->code])]
                : ['required', 'string', 'max:255', 'regex:'.ShippingCarrier::CODE_PATTERN],
            'name' => ['required', 'string', 'max:255'],
            'expected_state' => $this->route('carrier') instanceof ShippingCarrier
                ? ['required', 'string', 'regex:'.ShippingCarrier::STATE_PATTERN] : ['prohibited'],
            ...array_fill_keys([
                'id', 'uuid', 'adapter', 'is_active', 'default_api_url', 'reference_source',
                'reference_version', 'synced_at', 'deleted_at',
            ], ['prohibited']),
        ];
    }
}
