<?php

namespace App\Services\Central\Users;

use App\Enums\VerificationStatusEnum;
use App\Models\Central\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LegalVerificationService
{
    /** @return array<string, array> */
    public static function profileRules(): array
    {
        return [
            'legal_form' => ['sometimes', 'nullable', 'string', 'max:255'],
            'activity_nature' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nif' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nis' => ['sometimes', 'nullable', 'string', 'max:255'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'artisan_card_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'legal_address' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'share_capital' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
        ];
    }

    /** Prépare les champs ; seul le service d’approbation enregistre la décision. */
    public function decisionAttributes(User $actor, User $target, int $version, bool $validated): array
    {
        if ($version < 0 || ($target->legal_profile_version ?? 0) !== $version) {
            throw ValidationException::withMessages(['legal_profile_version' => 'Le dossier a changé. Rechargez sa fiche avant de décider.']);
        }
        if ($validated) {
            Validator::make($target->only(array_keys(self::profileRules())), self::profileRules())->validate();
        }

        return [
            'legal_profile_version' => $target->legal_profile_version ?? 1,
            'legal_verification_status' => $validated ? VerificationStatusEnum::VERIFIED : VerificationStatusEnum::FAILED,
            'legal_verified_at' => $validated ? now() : null,
            'legal_verified_by_id' => $validated ? $actor->id : null,
        ];
    }
}
