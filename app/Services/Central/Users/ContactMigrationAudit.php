<?php

namespace App\Services\Central\Users;

use App\Models\Central\User;
use App\Models\Central\Country;
use Illuminate\Validation\ValidationException;
use LogicException;

class ContactMigrationAudit
{
    public function __construct(private PhoneNumberNormalizer $phones) {}

    /** @return array{invalid: list<string>, duplicates: list<list<string>>, changed: int} */
    public function inspect(): array
    {
        if (tenancy()->initialized) {
            throw new LogicException('Cet audit concerne uniquement les comptes centraux.');
        }

        $owners = [];
        $invalid = [];
        $changed = 0;
        $countryCodes = Country::withTrashed()->pluck('code')->map(fn ($code) => strtoupper(trim($code)))->all();
        $hasPhoneCountry = (new User)->getConnection()->getSchemaBuilder()->hasColumn('users', 'phone_country_id');
        $query = User::withTrashed()->select(['id', 'uuid', 'country_id', 'phone', ...($hasPhoneCountry ? ['phone_country_id'] : [])])
            ->with(['country' => fn ($query) => $query->withTrashed()]);
        if ($hasPhoneCountry) {
            $query->with('phoneCountry');
        }
        $query
            ->chunkById(200, function ($users) use (&$owners, &$invalid, &$changed, $hasPhoneCountry, $countryCodes): void {
                foreach ($users as $user) {
                    if ($user->phone === null || trim($user->phone) === '') {
                        continue;
                    }
                    try {
                        if ($hasPhoneCountry && $user->phone_country_id !== null) {
                            $value = $this->phones->normalize($user->phone, $user->phoneCountry?->code ?? '');
                        } else {
                            $historical = $this->phones->historical($user->phone, $user->country?->code ?? '');
                            if (! in_array($historical['region'], $countryCodes, true)) {
                                $invalid[] = $user->uuid;
                                continue;
                            }
                            $value = $historical['value'];
                        }
                    } catch (ValidationException) {
                        $invalid[] = $user->uuid;
                        continue;
                    }
                    $owners[$value][] = $user->uuid;
                    if ($value !== $user->phone) {
                        $changed++;
                    }
                }
            });

        return [
            'invalid' => array_values(array_unique($invalid)),
            'duplicates' => array_values(array_filter(array_map(fn (array $users) => array_values(array_unique($users)), $owners), fn (array $users): bool => count($users) > 1)),
            'changed' => $changed,
        ];
    }
}
