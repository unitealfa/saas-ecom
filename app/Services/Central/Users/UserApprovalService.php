<?php

namespace App\Services\Central\Users;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class UserApprovalService
{
    public function __construct(private AuthorizationMutation $mutations, private LegalVerificationService $legal) {}

    /** @return LengthAwarePaginator<User> */
    public function pending(User $actor): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);

        return User::where('status', StatusEnum::PENDING->value)
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email', 'phone', 'country_id', 'status', 'legal_profile_version', 'legal_verification_status', 'created_at'])
            ->with(['country:id,name_fr', 'roles:id,guard_name,is_super_admin'])
            ->orderBy('created_at')->orderBy('id')->paginate(20);
    }

    public function decide(User $actor, User $target, bool $approved, ?string $reason, int $version, StatusEnum $expectedStatus): void
    {
        $this->mutations->run(function () use ($actor, $target, $approved, $reason, $version, $expectedStatus): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($approved ? 'approve' : 'reject', $target);
            if ($target->status !== $expectedStatus) {
                throw ValidationException::withMessages(['expected_status' => 'Une autre décision a déjà modifié ce compte. Rechargez sa fiche.']);
            }
            $this->recordDecision($actor, $target, $approved, $reason, $version);
        });
    }

    public function approveCreatedMerchant(User $actor, User $target): void
    {
        if (tenancy()->initialized || ! $target->wasRecentlyCreated || $target->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Initial approval requires the merchant creation transaction.');
        }
        Gate::forUser($actor)->authorize('create', User::class);
        $current = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
        abort_unless($current->status === StatusEnum::ACTIVE && $current->legal_verification_status === null
            && ! $current->roles()->where('guard_name', 'central')->exists(), 403);
        $this->recordDecision($actor, $current, true, null, $current->legal_profile_version ?? 0, true);
        $target->setRawAttributes($current->getAttributes(), true);
    }

    private function recordDecision(User $actor, User $target, bool $approved, ?string $reason, int $version, bool $creation = false): void
    {
        $reason = $approved ? null : trim($reason ?? '');
        if (! $approved) {
            Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']])->validate();
        }
        $previousStatus = $creation ? null : $target->status->value;
        $previousLegalStatus = $target->legal_verification_status?->value;
        $attributes = $this->legal->decisionAttributes($actor, $target, $version, $approved);
        $target->forceFill(['status' => $approved ? StatusEnum::ACTIVE : StatusEnum::REJECTED, ...$attributes]);
        if (! $approved) {
            $target->remember_token = null;
            Password::broker('central')->deleteToken($target);
        }
        $target->save();
        $properties = [
            'previous_status' => $previousStatus, 'status' => $target->status->value,
            'previous_legal_status' => $previousLegalStatus, 'legal_status' => $target->legal_verification_status->value,
            'legal_profile_version' => $target->legal_profile_version, 'reason' => $reason,
            'source' => $creation ? 'admin_creation' : 'admin_decision',
        ];
        activity()->causedBy($actor)->performedOn($target)
            ->event($approved ? 'central_account_approved' : 'central_account_rejected')
            ->withProperties($properties)->log($approved ? 'Compte et dossier professionnel approuvés.' : 'Compte et dossier professionnel refusés.');
        activity()->causedBy($actor)->performedOn($target)
            ->event($approved ? 'central_legal_validated' : 'central_legal_rejected')
            ->withProperties($properties)->log($approved ? 'Dossier validé avec l’approbation du compte.' : 'Dossier rejeté avec le refus du compte.');
    }
}
