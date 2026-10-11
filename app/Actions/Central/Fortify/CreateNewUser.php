<?php

namespace App\Actions\Central\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Central\Country;
use App\Models\User;
use App\Services\Central\DefaultSubscription;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private DefaultSubscription $subscriptions) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'country_uuid' => ['required', 'uuid', Rule::exists(Country::class, 'uuid')->where('is_active', true)->whereNull('deleted_at')],
        ])->validate();

        return (new User)->getConnection()->transaction(function () use ($input): User {
            $country = Country::where('uuid', $input['country_uuid'])->where('is_active', true)->lockForUpdate()->first();
            if ($country === null) {
                throw ValidationException::withMessages(['country_uuid' => 'Ce pays n’est plus disponible.']);
            }
            $user = new User([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $user->country_id = $country->id;
            $user->save();
            $this->subscriptions->ensure($user);

            return $user;
        }, 3);
    }
}
