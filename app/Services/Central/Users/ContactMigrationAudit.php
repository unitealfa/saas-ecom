<?php

namespace App\Services\Central\Users;

use App\Models\Central\Country;
use App\Models\Central\User;
use Illuminate\Validation\ValidationException;
use LogicException;

class ContactMigrationAudit
{
    private const int CHUNK_SIZE = 200;

    public function __construct(private PhoneNumberNormalizer $phones) {}

    public function inspect(): array
    {
        if (tenancy()->initialized) {
            throw new LogicException('Cet audit concerne uniquement les comptes centraux.');
        }

        $regions = $this->knownRegions();
        $hasPhoneCountry = $this->hasPhoneCountryColumn();

        $owners = [];
        $invalid = [];
        $changed = 0;

        foreach ($this->users($hasPhoneCountry)->lazyById(self::CHUNK_SIZE) as $user) {
            if (trim((string) $user->phone) === '') {
                continue;
            }

            $value = $this->normalizedPhone($user, $hasPhoneCountry, $regions);

            if ($value === null) {
                $invalid[] = $user->uuid;

                continue;
            }

            $owners[$value][] = $user->uuid;

            if ($value !== $user->phone) {
                $changed++;
            }
        }

        return [
            'invalid' => $invalid,
            'duplicates' => array_values(array_filter($owners, fn (array $uuids): bool => count($uuids) > 1)),
            'changed' => $changed,
        ];
    }

    private function normalizedPhone(User $user, bool $hasPhoneCountry, array $regions): ?string
    {
        try {
            if ($hasPhoneCountry && $user->phone_country_id !== null) {
                return $this->phones->normalize($user->phone, $user->phoneCountry?->code ?? '');
            }

            $historical = $this->phones->historical($user->phone, $user->country?->code ?? '');

            return isset($regions[$historical['region']]) ? $historical['value'] : null;
        } catch (ValidationException) {
            return null;
        }
    }

    private function knownRegions(): array
    {
        return Country::withTrashed()->pluck('code')
            ->map(fn ($code): string => strtoupper(trim((string) $code)))
            ->flip()
            ->all();
    }

    private function hasPhoneCountryColumn(): bool
    {
        return (new User)->getConnection()->getSchemaBuilder()->hasColumn('users', 'phone_country_id');
    }

    private function users(bool $hasPhoneCountry)
    {
        return User::withTrashed()
            ->select(['id', 'uuid', 'country_id', 'phone', ...($hasPhoneCountry ? ['phone_country_id'] : [])])
            ->with(['country' => fn ($query) => $query->withTrashed()])
            ->when($hasPhoneCountry, fn ($query) => $query->with('phoneCountry'));
    }
}