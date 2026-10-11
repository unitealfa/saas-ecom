<?php

namespace App\Actions\Central\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Central\Country;
use App\Models\User;
use App\Services\Central\DefaultSubscription;
use Illuminate\Support\Facades\Validator;
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
        ])->validate();

        return (new User)->getConnection()->transaction(function () use ($input): User {
            $user = new User([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $user->country_id = Country::where('code', 'DZ')->where('is_active', true)->firstOrFail()->id;
            $user->save();
            $this->subscriptions->ensure($user);

            return $user;
        }, 3);
    }
}
