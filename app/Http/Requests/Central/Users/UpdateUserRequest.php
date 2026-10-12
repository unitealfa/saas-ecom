<?php

namespace App\Http\Requests\Central\Users;

use App\DTOs\Central\Users\UserProfileData;
use App\Models\Central\User;
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
            'locale' => ['sometimes', 'required', 'string', 'regex:/^[a-z]{2,3}([_-][A-Za-z]{2,4})?$/', 'max:255'],
            'legal_form' => ['sometimes', 'nullable', 'string', 'max:255'],
            'activity_nature' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nif' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nis' => ['sometimes', 'nullable', 'string', 'max:255'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'artisan_card_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'legal_address' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'share_capital' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'country_uuid' => ['required', 'uuid', Rule::exists('countries', 'uuid')->whereNull('deleted_at')
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $user->country_id))],
            'password' => ['prohibited'], 'roles' => ['prohibited'], 'status' => ['prohibited'],
            'email_verified_at' => ['prohibited'], 'is_super_admin' => ['prohibited'],
            'phone_verified_at' => ['prohibited'], 'whatsapp_verified_at' => ['prohibited'],
            'legal_profile_version' => ['prohibited'], 'legal_verification_status' => ['prohibited'],
            'legal_verified_at' => ['prohibited'], 'legal_verified_by_id' => ['prohibited'],
            'last_login_at' => ['prohibited'], 'two_factor_secret' => ['prohibited'],
        ];
    }

    public function toDto(): UserProfileData
    {
        $data = $this->validated();

        return new UserProfileData($data['last_name'], $data['first_name'] ?? null, $data['email'],
            $data['phone'] ?? null, $data['country_uuid'],
            array_intersect_key($data, array_flip(['locale', ...UserProfileData::PROFESSIONAL_FIELDS])));
    }
}
