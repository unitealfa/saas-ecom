<?php

namespace App\Services\Central\Users;

use App\DTOs\Central\Users\UserData;
use App\DTOs\Central\Users\UserProfileData;
use App\DTOs\Central\Users\UserSaveResult;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Countries\Country;
use App\Models\Central\Roles\Role;
use App\Models\Central\Users\User;
use App\Services\Central\Auth\AccountMailDelivery;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Roles\CentralRoleCatalog;
use App\Services\Central\Subscriptions\DefaultSubscription;
use Closure;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class UserManagementService
{
    private const int PER_PAGE = 20;

    private const int MIN_PHONE_DIGITS = 5;

    private const int TRANSACTION_ATTEMPTS = 3;

    private const string BROKER = 'central';

    private const string ABILITY_UPDATE = 'update';

    private const string ABILITY_UPDATE_STAFF = 'updateStaff';

    private const string ABILITY_OWN_PROFILE = 'updateOwnProfile';

    private const array UPDATE_ABILITIES = [
        self::ABILITY_UPDATE,
        self::ABILITY_UPDATE_STAFF,
        self::ABILITY_OWN_PROFILE,
    ];

    public function __construct(
        private AuthorizationMutation $mutations,
        private DefaultSubscription $subscriptions,
        private CentralUserAccess $access,
        private AccountMailDelivery $mail,
        private UserApprovalService $approvals,
        private PhoneNumberNormalizer $phones,
        private LegalVerificationService $legal,
    ) {}

    /** @return LengthAwarePaginator<int, User> */
    public function search(User $actor, string $search, ?StatusEnum $status = null): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);

        return User::query()
            ->select([
                'id', 'uuid', 'first_name', 'last_name', 'email', 'status', 'email_verified_at',
                'legal_verification_status', 'legal_profile_version', 'deleted_at',
            ])
            ->with(['roles:id,uuid,label,guard_name', 'profilePhoto:id,uuid,model_type,model_id'])
            ->when($status === StatusEnum::DELETED, fn (Builder $query) => $query->onlyTrashed())
            ->when($status !== null, fn (Builder $query) => $query->withStatus($status))
            ->when(trim($search) !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->orderBy('id')
            ->paginate(self::PER_PAGE);
    }

    /** @return LengthAwarePaginator<int, Activity> */
    public function history(User $actor, User $target): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewHistory', $target);

        return Activity::forSubject($target)->with('causer')->orderByDesc('id')->paginate(self::PER_PAGE);
    }

    public function create(User $actor, UserData $data): UserSaveResult
    {
        $emailSent = null;

        $user = $this->mutations->run(
            actor: $actor,
            ability: $data->staff ? 'createStaff' : 'create',
            loadTarget: fn (): string => User::class,
            mutation: function () use ($actor, $data, &$emailSent): User {
                $roles = $this->rolesForCreation($actor, $data);
                $user = $this->newUser($data);

                $user->save();

                if (! $data->staff) {
                    $this->approvals->approveCreatedMerchant($actor, $user);
                }

                $this->subscriptions->ensure($user, $actor);

                if ($data->staff) {
                    $this->applyRoles($user, $roles);
                }

                $token = $this->broker()->createToken($user);

                DB::afterCommit(function () use ($user, $token, &$emailSent): void {
                    $emailSent = $this->mail->invitation($user, $token);
                });

                activity()->causedBy($actor)->performedOn($user)->event('account_invited')
                    ->withProperties(['staff' => $data->staff, 'role_uuids' => $roles->pluck('uuid')->all()])
                    ->log('Compte central créé et invité à choisir son mot de passe.');

                return $user;
            },
        );

        return new UserSaveResult($user, $emailSent);
    }

    public function update(
        User $actor,
        User $target,
        UserProfileData $data,
        string $ability = self::ABILITY_UPDATE,
        bool $notifyEmailChange = true,
    ): UserSaveResult {
        abort_unless(in_array($ability, self::UPDATE_ABILITIES, true), 403);

        $emailSent = null;
        $loadTarget = fn (): User => $this->lock($target);

        $mutation = function (User $target) use ($actor, $data, $notifyEmailChange, &$emailSent): User {
            $emailChanged = $target->email !== strtolower(trim($data->email));
            $wasVerified = $target->hasVerifiedEmail();

            if ($emailChanged) {
                $this->broker()->deleteToken($target);
            }

            $this->fillProfile($actor, $target, $data);

            if ($emailChanged) {
                $target->email_verified_at = null;
            }

            if ($target->isDirty('phone')) {
                $target->forceFill(['phone_verified_at' => null, 'whatsapp_verified_at' => null]);
            }

            $fields = array_keys($target->getDirty());

            $target->save();

            if ($emailChanged && $notifyEmailChange) {
                $token = $wasVerified ? null : $this->broker()->createToken($target);

                DB::afterCommit(function () use ($target, $token, &$emailSent): void {
                    $emailSent = $token === null
                        ? $this->mail->verification($target)
                        : $this->mail->invitation($target, $token);
                });
            }

            activity()->causedBy($actor)->performedOn($target)->event('central_profile_updated')
                ->withProperties(['changed_fields' => $fields])
                ->log('Informations du compte central modifiées.');

            return $target;
        };

        $user = $ability === self::ABILITY_OWN_PROFILE
            ? $this->updateOwnProfile($actor, $loadTarget, $mutation)
            : $this->mutations->run($actor, $ability, $loadTarget, $mutation);

        $target->setRawAttributes($user->getAttributes(), true)->unsetRelations();

        return new UserSaveResult($user, $emailSent);
    }

    public function resendInvitation(User $actor, User $target): UserSaveResult
    {
        $emailSent = null;

        $user = $this->mutations->run(
            actor: $actor,
            ability: 'resendInvitation',
            loadTarget: fn (): User => $this->lock($target),
            mutation: function (User $target) use ($actor, &$emailSent): User {
                if ($target->hasVerifiedEmail()) {
                    throw ValidationException::withMessages([
                        'invitation' => 'Ce compte a déjà vérifié son adresse e-mail.',
                    ]);
                }

                $token = $this->broker()->createToken($target);

                DB::afterCommit(function () use ($target, $token, &$emailSent): void {
                    $emailSent = $this->mail->invitation($target, $token);
                });

                activity()->causedBy($actor)->performedOn($target)->event('account_invitation_requested')
                    ->log('Renvoi de l’invitation centrale demandé.');

                return $target;
            },
        );

        return new UserSaveResult($user, $emailSent);
    }

    public function changeStatus(User $actor, User $target, StatusEnum $status, bool $staffOnly = false): void
    {
        $action = $status === StatusEnum::SUSPENDED ? 'suspend' : 'activate';

        $this->mutations->run(
            actor: $actor,
            ability: $staffOnly ? $action.'Staff' : $action,
            loadTarget: fn (): User => $this->lock($target),
            mutation: function (User $target) use ($actor, $status): void {
                $this->assertTransitionAllowed($target, $status);

                $target->status = $this->resultingStatus($target, $status);
                $target->save();

                if ($status === StatusEnum::SUSPENDED) {
                    $this->invalidateAccountAccess($target);
                }

                activity()->causedBy($actor)->performedOn($target)->event('central_account_status_updated')
                    ->withProperties(['status' => $target->status->value, 'requested_status' => $status->value])
                    ->log('État du compte central modifié.');
            },
        );
    }

    public function archive(User $actor, User $target, bool $staffOnly = false): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: $staffOnly ? 'archiveStaff' : 'archive',
            loadTarget: fn (): User => $this->lock($target),
            mutation: function (User $target) use ($actor): void {
                $target->status = StatusEnum::DELETED;
                $target->save();

                $this->invalidateAccountAccess($target);

                $target->delete();

                activity()->causedBy($actor)->performedOn($target)->event('central_account_archived')
                    ->log('Compte central archivé ; données et boutiques conservées.');
            },
        );
    }

    /** @param list<string> $roleUuids */
    public function assignRoles(User $actor, User $target, array $roleUuids): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: 'assignRoles',
            loadTarget: fn (): User => $this->lock($target),
            mutation: function (User $target) use ($actor, $roleUuids): void {
                $before = $this->roleAssignments($target);
                $roles = $this->rolesToAssign($actor, $target, $roleUuids);
                $revokedPermissions = $roles->isEmpty() ? $target->permissions()->pluck('uuid')->all() : [];

                $this->applyRoles($target, $roles);

                if ($revokedPermissions !== []) {
                    $target->syncPermissions([]);
                }

                activity()->causedBy($actor)->performedOn($target)->event('central_roles_updated')
                    ->withProperties([
                        'role_uuids' => $roles->pluck('uuid')->all(),
                        'revoked_permission_uuids' => $revokedPermissions,
                    ])
                    ->log('Rôles centraux attribués via Spatie.');

                $after = $this->roleAssignments($target);

                if ($before !== $after) {
                    activity()->causedBy($actor)->performedOn($target)->event('central_staff_roles_replaced')
                        ->withProperties(['before' => $before, 'after' => $after])
                        ->log('Attributions des rôles du personnel SaaS modifiées.');
                }
            },
        );
    }

    /** @return Collection<int, Role> */
    public function availableRoles(User $actor): Collection
    {
        $gate = Gate::forUser($actor);

        return Role::forCentralGuard()
            ->where('is_super_admin', false)
            ->with('permissionComposition')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role): bool => $gate->allows('assign', $role) && $this->hasOnlyCatalogPermissions($role))
            ->values();
    }

    /** @param Builder<User> $query */
    private function applySearch(Builder $query, string $search): void
    {
        $pattern = '%'.trim($search).'%';
        $digits = preg_replace('/[^0-9]/', '', $search);

        $query->where(function (Builder $query) use ($pattern, $digits): void {
            $query->where('first_name', 'like', $pattern)
                ->orWhere('last_name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern)
                ->orWhere('phone', 'like', $pattern);

            if (strlen($digits) >= self::MIN_PHONE_DIGITS) {
                $query->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%');
            }
        });
    }

    /** @return Collection<int, Role> */
    private function rolesForCreation(User $actor, UserData $data): Collection
    {
        if (! $data->staff && $data->roleUuids !== []) {
            throw ValidationException::withMessages(['roles' => 'Un compte commerçant est créé sans rôle SaaS.']);
        }

        if ($data->staff && $data->professionalAttributes !== []) {
            throw ValidationException::withMessages([
                'legal_form' => 'Le personnel SaaS ne possède pas de dossier professionnel commerçant.',
            ]);
        }

        if (! $data->staff) {
            return collect();
        }

        $roles = $this->delegableRoles($actor, $data->roleUuids);

        if ($roles->isEmpty()) {
            throw ValidationException::withMessages(['roles' => 'Sélectionnez au moins un rôle pour le personnel SaaS.']);
        }

        return $roles;
    }

    private function newUser(UserData $data): User
    {
        $country = Country::where('uuid', $data->countryUuid)->active()->lockForUpdate()->first();

        if ($country === null) {
            throw ValidationException::withMessages(['country_uuid' => 'Ce pays n’est plus disponible.']);
        }

        $user = new User([
            'last_name' => $data->lastName,
            'first_name' => $data->firstName,
            'email' => $data->email,
            'password' => Str::random(64),
        ]);

        $user->country_id = $country->id;
        $user->forceFill($this->phoneAttributes($data->phone, $data->phoneCountryUuid, $country));
        $user->status = StatusEnum::ACTIVE;

        if (! $data->staff) {
            Validator::make($data->professionalAttributes, LegalVerificationService::profileRules())->validate();

            $user->forceFill(array_intersect_key(
                $data->professionalAttributes,
                array_flip(UserProfileData::PROFESSIONAL_FIELDS),
            ));
        }

        return $user;
    }

    private function updateOwnProfile(User $actor, Closure $loadTarget, Closure $mutation): User
    {
        return DB::transaction(function () use ($actor, $loadTarget, $mutation): User {
            $target = $loadTarget();

            Gate::forUser($actor)->authorize(self::ABILITY_OWN_PROFILE, $target);

            return $mutation($target);
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function fillProfile(User $actor, User $target, UserProfileData $data): void
    {
        $country = Country::where('uuid', $data->countryUuid)
            ->where(fn (Builder $query) => $query->active()->orWhere('id', $target->country_id))
            ->lockForUpdate()
            ->firstOrFail();

        $professionalFields = array_flip(UserProfileData::PROFESSIONAL_FIELDS);
        $attributes = array_intersect_key($data->attributes, ['locale' => 0, ...$professionalFields]);
        $isStaff = $this->access->isStaff($target);

        if (array_intersect_key($attributes, $professionalFields) !== []) {
            if ($isStaff) {
                throw ValidationException::withMessages([
                    'legal_form' => 'Le personnel SaaS ne possède pas de profil professionnel vendeur.',
                ]);
            }

            Gate::forUser($actor)->authorize('viewLegal', $target);

            Validator::make($attributes, LegalVerificationService::profileRules())->validate();
        }

        $target->forceFill([
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
            'email' => strtolower(trim($data->email)),
            'country_id' => $country->id,
            ...$this->phoneAttributes($data->phone, $data->phoneCountryUuid, $country, $target),
            ...$attributes,
        ]);

        if (! $isStaff) {
            $this->legal->versionChangedProfile($target);
        }
    }

    /** @return array{phone: string|null, phone_country_id: int|null} */
    private function phoneAttributes(?string $value, ?string $countryUuid, Country $profileCountry, ?User $user = null): array
    {
        if ($value === null || trim($value) === '') {
            return ['phone' => null, 'phone_country_id' => null];
        }

        $currentCountryId = $user?->phone_country_id;

        $country = $countryUuid !== null
            ? Country::withTrashed()->where('uuid', $countryUuid)->lockForUpdate()->first()
            : Country::withTrashed()->whereKey($currentCountryId ?? $profileCountry->id)->lockForUpdate()->first();

        if ($country === null) {
            throw ValidationException::withMessages(['phone_country_uuid' => 'Choisissez un pays actif pour le téléphone.']);
        }

        $normalized = $this->phones->normalize($value, $country->code);
        $inactive = ! $country->is_active || $country->trashed();

        if ($inactive && ($currentCountryId !== $country->id || $normalized !== $user->phone)) {
            throw ValidationException::withMessages([
                'phone_country_uuid' => 'Choisissez un pays actif pour un nouveau numéro.',
            ]);
        }

        return ['phone' => $normalized, 'phone_country_id' => $country->id];
    }

    private function assertTransitionAllowed(User $target, StatusEnum $status): void
    {
        $allowed = in_array($status, [StatusEnum::ACTIVE, StatusEnum::SUSPENDED], true)
            && ($status !== StatusEnum::ACTIVE || in_array($target->status, [StatusEnum::SUSPENDED, StatusEnum::INACTIVE], true));

        if (! $allowed) {
            throw ValidationException::withMessages(['status' => 'Cette transition de statut n’est pas disponible.']);
        }
    }

    private function resultingStatus(User $target, StatusEnum $status): StatusEnum
    {
        $needsReview = $status === StatusEnum::ACTIVE
            && ! $this->access->isStaff($target)
            && $target->legal_verification_status !== VerificationStatusEnum::VERIFIED;

        return $needsReview ? StatusEnum::PENDING : $status;
    }

    private function invalidateAccountAccess(User $target): void
    {
        $target->forceFill(['remember_token' => null])->save();

        $this->broker()->deleteToken($target);

        $target->getConnection()->table('sessions')->where('user_id', $target->id)->delete();
    }

    /**
     * @param  list<string>  $roleUuids
     * @return Collection<int, Role>
     */
    private function rolesToAssign(User $actor, User $target, array $roleUuids): Collection
    {
        $roles = $this->delegableRoles($actor, $roleUuids);
        $editableUuids = $this->availableRoles($actor)->pluck('uuid');

        $preserved = $target->roles()->with('permissionComposition')->get()
            ->reject(fn (Role $role): bool => $editableUuids->contains($role->uuid));

        return $roles->merge($preserved)->unique('id');
    }

    /** @return array<int, array{role_uuid: string, assigned_at: string}> */
    private function roleAssignments(User $target): array
    {
        return $target->roles()->orderBy('roles.id')->get()
            ->map(fn (Role $role): array => [
                'role_uuid' => $role->uuid,
                'assigned_at' => $role->pivot->assigned_at->toIso8601String(),
            ])
            ->all();
    }

    private function hasOnlyCatalogPermissions(Role $role): bool
    {
        return $role->permissionComposition->isNotEmpty()
            && $role->permissionComposition->every(
                fn ($permission): bool => array_key_exists($permission->name, CentralRoleCatalog::PERMISSIONS),
            );
    }

    /**
     * @param  list<string>  $uuids
     * @return Collection<int, Role>
     */
    private function delegableRoles(User $actor, array $uuids): Collection
    {
        $roles = Role::forCentralGuard()->whereIn('uuid', $uuids)->orderBy('id')->lockForUpdate()->get();

        if ($roles->count() !== count(array_unique($uuids))) {
            throw ValidationException::withMessages(['roles' => 'Un rôle sélectionné n’est plus disponible.']);
        }

        $gate = Gate::forUser($actor);
        $permissionIds = [];

        foreach ($roles as $role) {
            $gate->authorize('assign', $role);

            $permissions = $role->permissionComposition()->get();

            if ($role->getAttribute('is_super_admin') || $permissions->isEmpty()) {
                throw ValidationException::withMessages([
                    'roles' => 'Le rôle Super Admin et les rôles vides ne sont pas attribuables ici.',
                ]);
            }

            foreach ($permissions as $permission) {
                abort_unless(array_key_exists($permission->name, CentralRoleCatalog::PERMISSIONS), 403);

                if (! $role->usesIndividualPermissions() && in_array($permission->getKey(), $permissionIds, true)) {
                    throw ValidationException::withMessages(['roles' => 'Les rôles sélectionnés partagent une permission.']);
                }

                $permissionIds[] = $permission->getKey();
            }
        }

        return $roles;
    }

    /** @param Collection<int, Role> $roles */
    private function applyRoles(User $user, Collection $roles): void
    {
        try {
            $user->syncRoles($roles);
        } catch (LogicException) {
            throw ValidationException::withMessages(['roles' => 'Ces rôles recoupent une attribution existante.']);
        }
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
