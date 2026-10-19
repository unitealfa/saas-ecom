<?php

namespace App\Http\Requests\Central\Users;

use App\Models\Central\User;
use App\Services\Central\Users\UserContactService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $target = $this->route('user') ?? $actor;
        abort_unless($actor instanceof User && $target instanceof User, 403);
        app(UserContactService::class)->authorize($actor, $target);

        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['email', 'phone'])],
            'value' => ['required', 'string', 'max:255'],
            'country_uuid' => ['nullable', 'required_if:kind,phone', 'uuid'],
            'verified_at' => ['prohibited'], 'is_primary' => ['prohibited'], 'user_id' => ['prohibited'],
        ];
    }
}
