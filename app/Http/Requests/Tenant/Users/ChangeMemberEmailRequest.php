<?php

namespace App\Http\Requests\Tenant\Users;

use App\Models\Tenant\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ChangeMemberEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('tenant');
        $member = $this->route('member');

        return $actor instanceof User && $member instanceof User && Gate::forUser($actor)->allows('manageMembership', $member);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('tenant.users', 'email')],
            'expected_state' => ['required', 'string', 'size:64'],
        ];
    }
}
