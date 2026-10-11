<?php

namespace App\DTOs\Central;

final readonly class UserProfileData
{
    public const PROFESSIONAL_FIELDS = ['legal_form', 'activity_nature', 'nif', 'nis', 'registration_number', 'artisan_card_number', 'legal_address', 'share_capital'];

    public function __construct(
        public string $lastName,
        public ?string $firstName,
        public string $email,
        public ?string $phone,
        public string $countryUuid,
        /** @var array<string, string|int|float|null> */
        public array $attributes = [],
    ) {}
}
