<?php

namespace App\Http\Requests\Tenant\Shop;

use App\Models\Tenant\Shop\ShopAddress;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Shop\ShopProfileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreShopAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('tenant');
        if (! $actor instanceof User) {
            return false;
        }
        $shop = app(ShopProfileService::class)->shop();
        $uuid = $this->route('address');
        if ($uuid === null) {
            return Gate::forUser($actor)->allows('create', [ShopAddress::class, $shop]);
        }
        $address = ShopAddress::query()->where('shop_id', $shop->id)->where('uuid', $uuid)->first();

        return $address !== null && Gate::forUser($actor)->allows('update', $address);
    }

    protected function prepareForValidation(): void
    {
        foreach (['label', 'address', 'postal_code', 'map_url', 'phone'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
        if (is_string($this->input('opening_hours'))) {
            $raw = trim($this->input('opening_hours'));
            $this->merge(['opening_hours' => $raw === '' ? null : json_decode($raw, true)]);
            if ($raw !== '' && json_last_error() !== JSON_ERROR_NONE) {
                $this->merge(['opening_hours' => 'invalid-json']);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $editing = $this->route('address') !== null;

        return [
            'expected_state' => [$editing ? 'required' : 'prohibited', 'regex:/^[0-9a-f]{64}$/D'],
            'label' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:8000'],
            'province_uuid' => ['nullable', 'uuid'],
            'municipality_uuid' => ['nullable', 'uuid'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90', 'regex:/^-?[0-9]{1,3}(?:\.[0-9]{1,7})?$/D'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180', 'regex:/^-?[0-9]{1,3}(?:\.[0-9]{1,7})?$/D'],
            'map_url' => ['nullable', 'url:http,https', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'opening_hours' => ['nullable', 'array', 'max:7'],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_primary' => ['required', 'boolean'],
            'visible' => ['required', 'boolean'],
            'uuid' => ['prohibited'], 'shop_id' => ['prohibited'],
            'shop_address_id' => ['prohibited'], 'record_type' => ['prohibited'],
            'payload' => ['prohibited'], 'is_primary_slot' => ['prohibited'],
        ];
    }
}
