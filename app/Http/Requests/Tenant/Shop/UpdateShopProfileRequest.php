<?php

namespace App\Http\Requests\Tenant\Shop;

use App\Models\Tenant\Users\User;
use App\Services\Tenant\Shop\ShopProfileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateShopProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('tenant');

        return $actor instanceof User
            && Gate::forUser($actor)->allows('update', app(ShopProfileService::class)->shop());
    }

    protected function prepareForValidation(): void
    {
        foreach (['description', 'about', 'contact_email', 'contact_phone', 'contact_whatsapp', 'business_type', 'theme_code'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'regex:/^[0-9a-f]{64}$/D'],
            'description' => ['nullable', 'string', 'max:2000'],
            'about' => ['nullable', 'string', 'max:10000'],
            'business_type' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_whatsapp' => ['nullable', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'timezone'],
            'theme_code' => ['required', 'string', 'max:255'],
            'colors' => ['required', 'array', 'max:20'],
            'colors.*' => ['required', 'string', 'regex:/^#(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/D'],
            'cart_lifetime_days' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'logo' => ['nullable', 'file', 'image', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048', 'dimensions:max_width=5000,max_height=5000'],
            'favicon' => ['nullable', 'file', 'image', 'mimetypes:image/png,image/jpeg,image/webp', 'max:1024', 'dimensions:max_width=5000,max_height=5000'],
            'shop_name' => ['prohibited'], 'tenant_uuid' => ['prohibited'],
            'uuid' => ['prohibited'], 'singleton' => ['prohibited'],
            'central_profile_version' => ['prohibited'], 'logo_media_id' => ['prohibited'],
            'favicon_media_id' => ['prohibited'], 'currency' => ['prohibited'],
            'shipping_tax_configuration' => ['prohibited'],
        ];
    }
}
