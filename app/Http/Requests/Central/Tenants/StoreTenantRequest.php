<?php

namespace App\Http\Requests\Central\Tenants;

use App\DTOs\Central\Tenants\TenantData;
use App\DTOs\Tenant\Shop\ShopSettings;
use App\Models\Central\Tenants\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('create', [Tenant::class, $this->route('user')]);
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('shop_name');
        $slug = $this->input('slug');
        if (is_string($slug)) {
            $slug = strtolower(trim($slug));
        }
        if (($slug === null || $slug === '') && is_string($name)) {
            $slug = Str::slug($name);
        }

        $this->merge([
            'shop_name' => is_string($name) ? trim($name) : $name,
            'slug' => $slug,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:55', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', 'not_in:www,admin,api,app,mail,support'],
            'creation_key' => ['required', 'uuid'],
            'settings' => ['sometimes', 'array:business_type,theme_code,colors,cart_lifetime_days'],
            'settings.business_type' => ['nullable', 'string', 'max:255'],
            'settings.theme_code' => ['nullable', 'string', 'max:255'],
            'settings.colors' => ['sometimes', 'array'],
            'settings.colors.*' => ['required', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/D'],
            'settings.cart_lifetime_days' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }

    public function toDto(): TenantData
    {
        $data = $this->validated();

        return new TenantData($data['shop_name'], $data['slug'], $data['creation_key'], ShopSettings::fromArray($data['settings'] ?? []));
    }
}
