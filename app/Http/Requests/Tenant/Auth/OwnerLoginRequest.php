<?php

namespace App\Http\Requests\Tenant\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OwnerLoginRequest extends FormRequest
{
    public const string NONCE = 'owner_login_nonce';

    public function authorize(): bool
    {
        $nonce = $this->session()->get(self::NONCE);
        $provided = $this->query('nonce');

        return tenancy()->initialized && $this->hasValidRelativeSignature()
            && is_string($nonce) && is_string($provided) && hash_equals($nonce, $provided);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['owner' => ['required', 'uuid'], 'tenant_uuid' => ['required', 'uuid'],
            'email_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'nonce' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']];
    }
}
