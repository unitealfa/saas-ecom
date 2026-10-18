<?php

namespace App\Actions\Central\Auth\Fortify;

use App\Models\User;
use App\DTOs\Central\Users\UserProfileData;
use App\Services\Central\Users\UserManagementService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(private UserManagementService $users) {}

    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = strtolower(trim($input['email']));
        }
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ])->validateWithBag('updateProfileInformation');

        $sameName = trim($input['name']) === $user->name;
        $this->users->update($user, $user, new UserProfileData(
            $sameName ? $user->last_name : trim($input['name']), $sameName ? $user->first_name : null,
            $input['email'], $user->phone, $user->country->uuid,
        ), ability: 'updateOwnProfile');
    }
}
