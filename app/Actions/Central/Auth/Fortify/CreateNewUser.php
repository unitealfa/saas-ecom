<?php

namespace App\Actions\Central\Auth\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Countries\Country;
use App\Models\User;
use App\Services\Central\Subscriptions\DefaultSubscription;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private DefaultSubscription $subscriptions) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
                if ($field === 'email') {
                    $input[$field] = strtolower($input[$field]);
                }
            }
        }
        Validator::make($input, [
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
            'country_uuid' => ['required', 'uuid', Rule::exists(Country::class, 'uuid')->where('is_active', true)->whereNull('deleted_at')],
        ], ['email.unique' => 'Cette adresse e-mail est déjà utilisée.'])->validate();

        return (new User)->getConnection()->transaction(function () use ($input): User {
            $country = Country::where('uuid', $input['country_uuid'])->active()->lockForUpdate()->first();
            if ($country === null) {
                throw ValidationException::withMessages(['country_uuid' => 'Ce pays n’est plus disponible.']);
            }
            $user = new User([
                'last_name' => $input['last_name'],
                'first_name' => $input['first_name'] ?? null,
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $user->country_id = $country->id;
            $user->phone = $input['phone'] ?? null;
            $user->status = StatusEnum::PENDING;
            $user->legal_profile_version = 1;
            $user->legal_verification_status = VerificationStatusEnum::PENDING;
            $user->save();
            $this->subscriptions->ensure($user);
            activity()->performedOn($user)->event('central_registration_requested')
                ->log('Inscription centrale en attente d’approbation administrative.');

            return $user;
        }, 3);
    }
}
