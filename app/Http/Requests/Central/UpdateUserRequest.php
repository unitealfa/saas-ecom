<?php

namespace App\Http\Requests\Central;

use App\DTOs\Central\UserProfileData;
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
        foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                if ($field === 'email') {
                    $value = strtolower($value);
                }
                if (in_array($field, ['first_name', 'phone'], true) && $value === '') {
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
            'country_uuid' => ['required', 'uuid', Rule::exists('countries', 'uuid')->whereNull('deleted_at')
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $user->country_id))],
            'password' => ['prohibited'], 'roles' => ['prohibited'], 'status' => ['prohibited'],
            'email_verified_at' => ['prohibited'], 'is_super_admin' => ['prohibited'],
        ];
    }

    public function toDto(): UserProfileData
    {
        $data = $this->validated();

        return new UserProfileData($data['last_name'], $data['first_name'] ?? null, $data['email'],
            $data['phone'] ?? null, $data['country_uuid']);
    }
}
