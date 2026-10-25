<?php

namespace App\Services\Central\Users;

use App\DTOs\Central\Users\UserProfileData;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LegalVerificationService
{
    private const array VERSIONED_FIELDS = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_country_id',
        'country_id',
    ];

    public function versionChangedProfile(User $target): void
    {
        if (! $target->isDirty([...self::VERSIONED_FIELDS, ...UserProfileData::PROFESSIONAL_FIELDS])) {
            return;
        }

        $target->legal_profile_version = ($target->legal_profile_version ?? 0) + 1;
    }

    /** @return array<string, list<string>> */
    public static function profileRules(): array
    {
        $text = ['sometimes', 'nullable', 'string', 'max:255'];

        return [
            'legal_form' => $text,
            'activity_nature' => $text,
            'nif' => $text,
            'nis' => $text,
            'registration_number' => $text,
            'artisan_card_number' => $text,
            'legal_address' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'share_capital' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
        ];
    }

    /** @return array{legal_profile_version: int, legal_verification_status: VerificationStatusEnum, legal_verified_at: CarbonImmutable|null, legal_verified_by_id: int|null} */
    public function decisionAttributes(User $actor, User $target, int $version, bool $validated): array
    {
        $current = $target->legal_profile_version ?? 0;

        if ($version < 0 || $current !== $version) {
            throw ValidationException::withMessages([
                'legal_profile_version' => 'Le dossier a changé. Rechargez sa fiche avant de décider.',
            ]);
        }

        if ($validated) {
            $rules = self::profileRules();

            Validator::make($target->only(array_keys($rules)), $rules)->validate();
        }

        return [
            'legal_profile_version' => $target->legal_profile_version ?? 1,
            'legal_verification_status' => $validated ? VerificationStatusEnum::VERIFIED : VerificationStatusEnum::FAILED,
            'legal_verified_at' => $validated ? now() : null,
            'legal_verified_by_id' => $validated ? $actor->id : null,
        ];
    }
}
