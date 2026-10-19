<?php

namespace App\Services\Central\Auth;

use Illuminate\Auth\EloquentUserProvider;

class ContactUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        if (! isset($credentials['email']) || ! is_string($credentials['email']) || tenancy()->initialized) {
            return null;
        }
        $email = strtolower(trim($credentials['email']));
        $model = $this->createModel();
        $hasContacts = $model->getConnection()->getSchemaBuilder()->hasTable('user_contacts');
        $query = $this->newModelQuery()->where(function ($query) use ($email, $hasContacts): void {
            $query->where('email', $email);
            if ($hasContacts) {
                $query->orWhereHas('contacts', fn ($contacts) => $contacts->where('kind', 'email')
                    ->where('value', $email)->whereNotNull('verified_at'));
            }
        });
        foreach ($credentials as $key => $value) {
            if ($key !== 'email' && ! str_contains($key, 'password')) {
                $query->where($key, $value);
            }
        }
        $user = $query->first();
        if ($user !== null) {
            $user->recoveryEmail = $email;
        }

        return $user;
    }
}
