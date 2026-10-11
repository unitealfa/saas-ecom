<?php

namespace App\DTOs\Central;

final readonly class UserProfileData
{
    public function __construct(
        public string $lastName,
        public ?string $firstName,
        public string $email,
        public ?string $phone,
        public string $countryUuid,
    ) {}
}
