<?php

namespace App\Http\Requests\Tenant\Shop;

use App\Models\Tenant\Shop\ShopSocialLink;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Shop\ShopProfileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreShopSocialLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('tenant');
        if (! $actor instanceof User) {
            return false;
        }
        $shop = app(ShopProfileService::class)->shop();
        $uuid = $this->route('link');
        if ($uuid === null) {
            return Gate::forUser($actor)->allows('create', [ShopSocialLink::class, $shop]);
        }
        $link = ShopSocialLink::query()->where('shop_id', $shop->id)->where('uuid', $uuid)->first();

        return $link !== null && Gate::forUser($actor)->allows('update', $link);
    }

    protected function prepareForValidation(): void
    {
        foreach (['label', 'network', 'url'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $editing = $this->route('link') !== null;

        return [
            'expected_state' => [$editing ? 'required' : 'prohibited', 'regex:/^[0-9a-f]{64}$/D'],
            'label' => ['nullable', 'string', 'max:255'],
            'network' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN][\pL\pN ._-]*$/u'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'address_uuid' => ['nullable', 'uuid'],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
            'visible' => ['required', 'boolean'],
            'uuid' => ['prohibited'], 'shop_id' => ['prohibited'], 'shop_address_id' => ['prohibited'],
            'record_type' => ['prohibited'], 'payload' => ['prohibited'], 'is_primary' => ['prohibited'],
        ];
    }
}
