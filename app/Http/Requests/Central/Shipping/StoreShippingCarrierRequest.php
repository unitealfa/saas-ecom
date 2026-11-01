<?php

namespace App\Http\Requests\Central\Shipping;

use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreShippingCarrierRequest extends FormRequest
{
    private const array FORBIDDEN_FIELDS = [
        'id', 'uuid', 'adapter', 'is_active', 'default_api_url',
        'reference_source', 'reference_version', 'synced_at', 'deleted_at',
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $carrier = $this->carrier();

        return $actor instanceof User
            && Gate::forUser($actor)->allows($carrier ? 'update' : 'create', $carrier ?? ShippingCarrier::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $carrier = $this->carrier();

        return [
            // Le code est immuable : à la modification, il doit rester celui du transporteur.
            'code' => $carrier
                ? ['required', 'string', Rule::in([$carrier->code])]
                : ['required', 'string', 'max:255', 'regex:'.ShippingCarrier::CODE_PATTERN],
            'name' => ['required', 'string', 'max:255'],
            'expected_state' => $carrier
                ? ['required', 'string', 'regex:'.ShippingCarrier::STATE_PATTERN]
                : ['prohibited'],
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.in' => 'Le code d’un transporteur ne peut pas être modifié.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtolower(trim($this->input('code')))]);
        }
    }

    private function carrier(): ?ShippingCarrier
    {
        $carrier = $this->route('carrier');

        return $carrier instanceof ShippingCarrier ? $carrier : null;
    }
}
