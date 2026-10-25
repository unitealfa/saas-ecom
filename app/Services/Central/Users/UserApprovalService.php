<?php

namespace App\Services\Central\Users;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class UserApprovalService
{
    private const int PER_PAGE = 20;

    private const int REASON_MAX_LENGTH = 1000;

    private const string GUARD = 'central';

    private const string BROKER = 'central';

    private const string SOURCE_CREATION = 'admin_creation';

    private const string SOURCE_DECISION = 'admin_decision';

    private const array APPROVED_EVENTS = [
        'central_account_approved' => 'Compte et dossier professionnel approuvés.',
        'central_legal_validated' => 'Dossier validé avec l’approbation du compte.',
    ];

    private const array REJECTED_EVENTS = [
        'central_account_rejected' => 'Compte et dossier professionnel refusés.',
        'central_legal_rejected' => 'Dossier rejeté avec le refus du compte.',
    ];

    public function __construct(
        private AuthorizationMutation $mutations,
        private LegalVerificationService $legal,
    ) {}

    public function pending(User $actor): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);

        return User::withStatus(StatusEnum::PENDING)
            ->select([
                'id', 'uuid', 'first_name', 'last_name', 'email', 'phone', 'country_id',
                'status', 'legal_profile_version', 'legal_verification_status', 'created_at',
            ])
            ->with(['country:id,name_fr', 'roles:id,guard_name,is_super_admin'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);
    }

    public function decide(User $actor, User $target, bool $approved, ?string $reason, int $version, StatusEnum $expectedStatus): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: $approved ? 'approve' : 'reject',
            loadTarget: fn (): User => $this->lock($target),
            mutation: function (User $target) use ($actor, $approved, $reason, $version, $expectedStatus): void {
                if ($target->status !== $expectedStatus) {
                    throw ValidationException::withMessages([
                        'expected_status' => 'Une autre décision a déjà modifié ce compte. Rechargez sa fiche.',
                    ]);
                }

                $this->recordDecision($actor, $target, $approved, $reason, $version);
            },
        );
    }

    public function approveCreatedMerchant(User $actor, User $target): void
    {
        if (tenancy()->initialized || ! $target->wasRecentlyCreated || DB::transactionLevel() === 0) {
            throw new LogicException('Initial approval requires the merchant creation transaction.');
        }

        Gate::forUser($actor)->authorize('create', User::class);

        $current = $this->lock($target);

        abort_unless(
            $current->status === StatusEnum::ACTIVE
                && $current->legal_verification_status === null
                && ! $current->roles()->where('guard_name', self::GUARD)->exists(),
            403,
        );

        $this->recordDecision($actor, $current, true, null, $current->legal_profile_version ?? 0, true);

        $target->setRawAttributes($current->getAttributes(), true);
    }

    private function recordDecision(User $actor, User $target, bool $approved, ?string $reason, int $version, bool $creation = false): void
    {
        $reason = $approved ? null : $this->validatedReason($reason);

        $previousStatus = $creation ? null : $target->status->value;
        $previousLegalStatus = $target->legal_verification_status?->value;

        $attributes = $this->legal->decisionAttributes($actor, $target, $version, $approved);

        $target->forceFill(['status' => $approved ? StatusEnum::ACTIVE : StatusEnum::REJECTED, ...$attributes]);

        if (! $approved) {
            $target->remember_token = null;
            $this->broker()->deleteToken($target);
        }

        $target->save();

        $properties = [
            'previous_status' => $previousStatus,
            'status' => $target->status->value,
            'previous_legal_status' => $previousLegalStatus,
            'legal_status' => $target->legal_verification_status->value,
            'legal_profile_version' => $target->legal_profile_version,
            'reason' => $reason,
            'source' => $creation ? self::SOURCE_CREATION : self::SOURCE_DECISION,
        ];

        $events = $approved ? self::APPROVED_EVENTS : self::REJECTED_EVENTS;

        foreach ($events as $event => $message) {
            activity()->causedBy($actor)->performedOn($target)
                ->event($event)
                ->withProperties($properties)
                ->log($message);
        }
    }

    private function validatedReason(?string $reason): string
    {
        $reason = trim($reason ?? '');

        Validator::make(
            ['reason' => $reason],
            ['reason' => ['required', 'string', 'max:'.self::REASON_MAX_LENGTH]],
        )->validate();

        return $reason;
    }

    private function lock(User $target): User
    {
        return User::whereKey($target->id)->lockForUpdate()->firstOrFail();
    }

    private function broker(): PasswordBroker
    {
        $broker = Password::broker(self::BROKER);

        assert($broker instanceof PasswordBroker);

        return $broker;
    }
}