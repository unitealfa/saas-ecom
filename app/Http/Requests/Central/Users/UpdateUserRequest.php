<?php

namespace App\Http\Requests\Central\Users;

use App\DTOs\Central\Users\UserProfileData;
use App\Models\Central\Users\User;
use App\Services\Central\Users\LegalVerificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('update', $this->route('user'));
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['first_name', 'last_name', 'email', 'phone', 'locale', ...UserProfileData::PROFESSIONAL_FIELDS] as $field) {
            if (! $this->exists($field)) {
                continue;
            }
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                if ($field === 'email') {
                    $value = strtolower($value);
                }
                if (! in_array($field, ['last_name', 'email', 'locale'], true) && $value === '') {
                    $value = null;
                }
            }
            $values[$field] = $value;
        }
        $this->merge($values);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('user');
        abort_unless($user instanceof User, 404);

        return [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'phone_country_uuid' => ['nullable', 'uuid', Rule::exists('countries', 'uuid')
                ->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')->orWhere('id', $user->phone_country_id))],
            'locale' => ['sometimes', 'required', 'string', 'regex:/^[a-z]{2,3}([_-][A-Za-z]{2,4})?$/', 'max:255'],
            ...LegalVerificationService::profileRules(),
            'country_uuid' => ['required', 'uuid', Rule::exists('countries', 'uuid')->whereNull('deleted_at')
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $user->country_id))],
            'password' => ['prohibited'],
            'roles' => ['prohibited'],
            'status' => ['prohibited'],
            'permissions' => ['prohibited'],
            'account_kind' => ['prohibited'],
            'guard_name' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'is_super_admin' => ['prohibited'],
            'phone_verified_at' => ['prohibited'],
            'whatsapp_verified_at' => ['prohibited'],
            'legal_profile_version' => ['prohibited'],
            'legal_verification_status' => ['prohibited'],
            'legal_verified_at' => ['prohibited'],
            'legal_verified_by_id' => ['prohibited'],
            'last_login_at' => ['prohibited'],
            'two_factor_secret' => ['prohibited'],
        ];
    }

    public function toDto(): UserProfileData
    {
        $data = $this->validated();

        return new UserProfileData(
            lastName: $data['last_name'],
            firstName: $data['first_name'] ?? null,
            email: $data['email'],
            phone: $data['phone'] ?? null,
            countryUuid: $data['country_uuid'],
            attributes: array_intersect_key($data, array_flip(['locale', ...UserProfileData::PROFESSIONAL_FIELDS])),
            phoneCountryUuid: $data['phone_country_uuid'] ?? null,
        );
    }

    public function messages(): array
    {
        return ['email.unique' => 'Cette adresse e-mail est déjà utilisée.'];
    }
}
