<?php

namespace App\Http\Requests\Central;

use App\DTOs\Central\UserData;
use App\Models\Central\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows(
            $this->input('account_kind') === 'staff' ? 'createStaff' : 'create', User::class,
        );
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $lastName = $this->input('last_name');
        $firstName = $this->input('first_name');

        $this->merge([
            'email' => is_string($email) ? strtolower(trim($email)) : $email,
            'last_name' => is_string($lastName) ? trim($lastName) : $lastName,
            'first_name' => is_string($firstName) ? (trim($firstName) ?: null) : $firstName,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:255'], 'first_name' => ['nullable', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'country_uuid' => ['required', 'uuid', Rule::exists('countries', 'uuid')->where('is_active', true)->whereNull('deleted_at')],
            'account_kind' => ['required', Rule::in(['merchant', 'staff'])],
            'roles' => [Rule::requiredIf($this->input('account_kind') === 'staff'), 'array'],
            'roles.*' => ['uuid', 'distinct', Rule::exists('roles', 'uuid')->where('guard_name', 'central')->where('is_super_admin', false)],
            'is_super_admin' => ['prohibited'], 'password' => ['prohibited'],
        ];
    }

    public function toDto(): UserData
    {
        $data = $this->validated();

        return new UserData($data['last_name'], $data['first_name'] ?? null, $data['email'],
            $data['country_uuid'], $data['account_kind'] === 'staff', $data['roles'] ?? []);
    }
}
