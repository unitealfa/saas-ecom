<?php

namespace App\Http\Requests\Tenant\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class AcceptTeamInvitationRequest extends FormRequest
{
    public function authorize(): bool { return tenancy()->initialized; }
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'first_name' => ['nullable', 'string', 'max:150'],
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ];
    }
}
