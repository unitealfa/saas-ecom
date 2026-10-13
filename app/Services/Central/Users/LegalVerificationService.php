<?php

namespace App\Services\Central\Users;

use App\Enums\VerificationStatusEnum;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LegalVerificationService
{
    public function __construct(private AuthorizationMutation $mutations) {}

    public function decide(User $actor, User $target, int $version, bool $validated, ?string $reason): void
    {
        $this->mutations->run(function () use ($actor, $target, $version, $validated, $reason): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($validated ? 'validateLegal' : 'rejectLegal', $target);
            if ($version < 1 || $target->legal_profile_version !== $version) {
                throw ValidationException::withMessages(['legal_profile_version' => 'Le dossier a changé. Rechargez sa fiche avant de décider.']);
            }
            $status = $validated ? VerificationStatusEnum::VERIFIED : VerificationStatusEnum::FAILED;
            if ($target->legal_verification_status === $status) {
                throw ValidationException::withMessages(['legal' => 'Cette décision est déjà enregistrée.']);
            }
            if ($validated && (! filled($target->legal_form) || ! filled($target->activity_nature)
                || ! filled($target->legal_address)
                || (! filled($target->getAttribute('registration_number')) && ! filled($target->getAttribute('artisan_card_number'))))) {
                throw ValidationException::withMessages(['legal' => 'Complétez la forme juridique, l’activité, l’adresse et le registre de commerce ou la carte artisan avant validation.']);
            }
            $target->forceFill([
                'legal_verification_status' => $status,
                'legal_verified_at' => $validated ? now() : null,
                'legal_verified_by_id' => $validated ? $actor->id : null,
            ])->save();
            activity()->causedBy($actor)->performedOn($target)
                ->event($validated ? 'central_legal_validated' : 'central_legal_rejected')
                ->withProperties(['legal_profile_version' => $version, 'reason' => $reason])
                ->log($validated ? 'Dossier professionnel validé.' : 'Vérification professionnelle rejetée.');
        });
    }
}
