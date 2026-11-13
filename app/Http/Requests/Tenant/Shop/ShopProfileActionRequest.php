<?php

namespace App\Http\Requests\Tenant\Shop;

use App\Models\Tenant\Shop\ShopAddress;
use App\Models\Tenant\Shop\ShopSocialLink;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Shop\ShopProfileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShopProfileActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('tenant');
        if (! $actor instanceof User) {
            return false;
        }
        $shop = app(ShopProfileService::class)->shop();
        if (($uuid = $this->route('address')) !== null) {
            $address = ShopAddress::query()->where('shop_id', $shop->id)->where('uuid', $uuid)->first();

            return $address !== null && Gate::forUser($actor)->allows('archive', $address);
        }
        if (($uuid = $this->route('link')) !== null) {
            $link = ShopSocialLink::query()->where('shop_id', $shop->id)->where('uuid', $uuid)->first();

            return $link !== null && Gate::forUser($actor)->allows('archive', $link);
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['expected_state' => ['required', 'regex:/^[0-9a-f]{64}$/D']];
    }
}
