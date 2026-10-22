<?php

namespace App\Http\Requests\Central\Users;

use App\DTOs\Central\Users\UserData;
use App\DTOs\Central\Users\UserProfileData;
use App\Models\Central\User;
use App\Services\Central\Users\LegalVerificationService;
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
        if (is_string($firstName)) {
            $firstName = trim($firstName);
        }

        $this->merge([
            'email' => is_string($email) ? strtolower(trim($email)) : $email,
            'last_name' => is_string($lastName) ? trim($lastName) : $lastName,
            'first_name' => $firstName === '' ? null : $firstName,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:255'],
            'phone_country_uuid' => ['nullable', 'uuid', Rule::exists('countries', 'uuid')->where('is_active', true)->whereNull('deleted_at')],
            'country_uuid' => ['required', 'uuid', Rule::exists('countries', 'uuid')->where('is_active', true)->whereNull('deleted_at')],
            'account_kind' => ['required', Rule::in(['merchant', 'staff'])],
            'roles' => [
                Rule::prohibitedIf($this->input('account_kind') === 'merchant'),
                Rule::requiredIf($this->input('account_kind') === 'staff'),
                'array',
            ],
            'roles.*' => [
                'uuid', 'distinct',
                Rule::exists('roles', 'uuid')->where('guard_name', 'central')->where('is_super_admin', false),
            ],
            'is_super_admin' => ['prohibited'],
            'password' => ['prohibited'],
            'permissions' => ['prohibited'],
            'guard_name' => ['prohibited'],
            'status' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'legal_profile_version' => ['prohibited'],
            'legal_verification_status' => ['prohibited'],
            'legal_verified_at' => ['prohibited'],
            'legal_verified_by_id' => ['prohibited'],
            ...($this->input('account_kind') === 'staff'
                ? array_fill_keys(UserProfileData::PROFESSIONAL_FIELDS, ['prohibited'])
                : LegalVerificationService::profileRules()),
        ];
    }

    public function toDto(): UserData
    {
        $data = $this->validated();

        return new UserData(
            lastName: $data['last_name'],
            firstName: $data['first_name'] ?? null,
            email: $data['email'],
            countryUuid: $data['country_uuid'],
            staff: $data['account_kind'] === 'staff',
            roleUuids: $data['roles'] ?? [],
            phone: $data['phone'] ?? null,
            professionalAttributes: array_intersect_key($data, array_flip(UserProfileData::PROFESSIONAL_FIELDS)),
            phoneCountryUuid: $data['phone_country_uuid'] ?? null,
        );
    }

    public function messages(): array
    {
        return ['email.unique' => 'Cette adresse e-mail est déjà utilisée.'];
    }
}
