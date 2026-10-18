<?php

namespace App\DTOs\Central\Users;

final readonly class UserData
{
    /** @param list<string> $roleUuids */
    public function __construct(
        public string $lastName,
        public ?string $firstName,
        public string $email,
        public string $countryUuid,
        public bool $staff,
        public array $roleUuids,
        public ?string $phone = null,
        /** @var array<string, string|int|float|null> */
        public array $professionalAttributes = [],
        public ?string $phoneCountryUuid = null,
    ) {}
}
