<?php

namespace App\Services\Central\Users;

use App\DTOs\Central\Users\UserData;
use App\DTOs\Central\Users\UserProfileData;
use App\DTOs\Central\Users\UserSaveResult;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Activity;
use App\Models\Central\Country;
use App\Models\Central\Role;
use App\Models\Central\User;
use App\Services\Central\Auth\AccountMailDelivery;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Roles\CentralRoleCatalog;
use App\Services\Central\Subscriptions\DefaultSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class UserManagementService
{
    public function __construct(
        private AuthorizationMutation $mutations,
        private DefaultSubscription $subscriptions,
        private CentralUserAccess $access,
        private AccountMailDelivery $mail,
        private UserApprovalService $approvals,
        private PhoneNumberNormalizer $phones,
        private LegalVerificationService $legal,
    ) {}

    /** @return LengthAwarePaginator<\App\Models\User> */
    public function search(User $actor, string $search, ?StatusEnum $status = null): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', User::class);
        $query = \App\Models\User::query()->select(['id', 'uuid', 'first_name', 'last_name', 'email', 'status', 'email_verified_at',
            'legal_verification_status', 'legal_profile_version', 'deleted_at'])
            ->with(['roles:id,uuid,label,guard_name', 'profilePhoto:id,uuid,model_type,model_id']);
        if ($status === StatusEnum::DELETED) {
            $query->onlyTrashed();
        }
        if ($status !== null) {
            $query->withStatus($status);
        }
        if (trim($search) !== '') {
            $query->where(function ($query) use ($search): void {
                $pattern = '%'.trim($search).'%';
                $query->where('first_name', 'like', $pattern)->orWhere('last_name', 'like', $pattern)->orWhere('email', 'like', $pattern)
                    ->orWhere('phone', 'like', $pattern);
                $digits = preg_replace('/[^0-9]/', '', $search);
                if (strlen($digits) >= 5) {
                    $query->orWhere('phone', 'like', '%'.ltrim($digits, '0').'%');
                }
            });
        }

        return $query->orderBy('id')->paginate(20);
    }

    /** @return LengthAwarePaginator<Activity> */
    public function history(User $actor, User $target): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewHistory', $target);

        return Activity::forSubject($target)->with('causer')->orderByDesc('id')->paginate(20);
    }

    public function create(User $actor, UserData $data): UserSaveResult
    {
        $emailSent = null;
        $user = $this->mutations->run(
            actor: $actor,
            ability: $data->staff ? 'createStaff' : 'create',
            loadTarget: fn () => User::class,
            mutation: function () use ($actor, $data, &$emailSent): User {
                if (! $data->staff && $data->roleUuids !== []) {
                    throw ValidationException::withMessages(['roles' => 'Un compte commerçant est créé sans rôle SaaS.']);
                }
                if ($data->staff && $data->professionalAttributes !== []) {
                    throw ValidationException::withMessages(['legal_form' => 'Le personnel SaaS ne possède pas de dossier professionnel commerçant.']);
                }
                $roles = $data->staff ? $this->delegableRoles($actor, $data->roleUuids) : collect();
                if ($data->staff && $roles->isEmpty()) {
                    throw ValidationException::withMessages(['roles' => 'Sélectionnez au moins un rôle pour le personnel SaaS.']);
                }
                $country = Country::where('uuid', $data->countryUuid)->active()->lockForUpdate()->first();
                if ($country === null) {
                    throw ValidationException::withMessages(['country_uuid' => 'Ce pays n’est plus disponible.']);
                }
                $user = new \App\Models\User([
                    'last_name' => $data->lastName, 'first_name' => $data->firstName,
                    'email' => $data->email, 'password' => Str::random(64),
                ]);
                $user->country_id = $country->id;
                $user->forceFill($this->phoneAttributes($data->phone, $data->phoneCountryUuid, $country));
                $user->status = StatusEnum::ACTIVE;
                if (! $data->staff) {
                    Validator::make($data->professionalAttributes, LegalVerificationService::profileRules())->validate();
                    $user->forceFill(array_intersect_key($data->professionalAttributes, array_flip(UserProfileData::PROFESSIONAL_FIELDS)));
                }
                $user->save();
                if (! $data->staff) {
                    $this->approvals->approveCreatedMerchant($actor, $user);
                }
                $this->subscriptions->ensure($user, $actor);
                if ($data->staff) {
                    $this->applyRoles($user, $roles);
                }
                $token = Password::broker('central')->createToken($user);
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

    public function update(User $actor, User $target, UserProfileData $data, string $ability = 'update', bool $notifyEmailChange = true): UserSaveResult
    {
        abort_unless(in_array($ability, ['update', 'updateStaff', 'updateOwnProfile'], true), 403);
        $emailSent = null;
        $loadTarget = fn () => \App\Models\User::whereKey($target->id)->lockForUpdate()->firstOrFail();
        $mutation = function (User $target) use ($actor, $data, $notifyEmailChange, &$emailSent): User {
            $emailChanged = $target->email !== strtolower(trim($data->email));
            $wasVerified = $target->hasVerifiedEmail();
            if ($emailChanged) {
                Password::broker('central')->deleteToken($target);
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
                $token = $wasVerified ? null : Password::broker('central')->createToken($target);
                DB::afterCommit(function () use ($target, $token, &$emailSent): void {
                    $emailSent = $token === null ? $this->mail->verification($target) : $this->mail->invitation($target, $token);
                });
            }
            activity()->causedBy($actor)->performedOn($target)->event('central_profile_updated')
                ->withProperties(['changed_fields' => $fields])->log('Informations du compte central modifiées.');

            return $target;
        };
        if ($ability === 'updateOwnProfile') {
            $user = DB::transaction(function () use ($actor, $loadTarget, $mutation): User {
                $target = $loadTarget();
                Gate::forUser($actor)->authorize('updateOwnProfile', $target);

                return $mutation($target);
            }, 3);
        } else {
            $user = $this->mutations->run($actor, $ability, $loadTarget, $mutation);
        }
        $target->setRawAttributes($user->getAttributes(), true)->unsetRelations();

        return new UserSaveResult($user, $emailSent);
    }

    private function fillProfile(User $actor, User $target, UserProfileData $data): void
    {
        $country = Country::where('uuid', $data->countryUuid)
            ->where(fn ($query) => $query->active()->orWhere('id', $target->country_id))->lockForUpdate()->firstOrFail();
        $attributes = array_intersect_key($data->attributes, array_flip(['locale', ...UserProfileData::PROFESSIONAL_FIELDS]));
        $isStaff = $this->access->isStaff($target);
        if (array_intersect_key($attributes, array_flip(UserProfileData::PROFESSIONAL_FIELDS)) !== []) {
            if ($isStaff) {
                throw ValidationException::withMessages(['legal_form' => 'Le personnel SaaS ne possède pas de profil professionnel vendeur.']);
            }
            Gate::forUser($actor)->authorize('viewLegal', $target);
            Validator::make($attributes, LegalVerificationService::profileRules())->validate();
        }

        $target->forceFill([
            'first_name' => $data->firstName, 'last_name' => $data->lastName,
            'email' => strtolower(trim($data->email)), 'country_id' => $country->id,
            ...$this->phoneAttributes($data->phone, $data->phoneCountryUuid, $country, $target),
            ...$attributes,
        ]);
        if (! $isStaff) {
            $this->legal->versionChangedProfile($target);
        }
    }

    /** @return array{phone: ?string, phone_country_id: ?int} */
    private function phoneAttributes(?string $value, ?string $countryUuid, Country $profileCountry, ?User $user = null): array
    {
        if ($value === null || trim($value) === '') {
            return ['phone' => null, 'phone_country_id' => null];
        }
        $country = $countryUuid !== null
            ? Country::withTrashed()->where('uuid', $countryUuid)->lockForUpdate()->first()
            : Country::withTrashed()->whereKey($user?->phone_country_id ?? $profileCountry->id)->lockForUpdate()->first();
        $currentCountry = $user?->phone_country_id;
        if ($country === null) {
            throw ValidationException::withMessages(['phone_country_uuid' => 'Choisissez un pays actif pour le téléphone.']);
        }
        $normalized = $this->phones->normalize($value, $country->code);
        if ((! $country->is_active || $country->trashed()) && ($currentCountry !== $country->id || $normalized !== $user?->phone)) {
            throw ValidationException::withMessages(['phone_country_uuid' => 'Choisissez un pays actif pour un nouveau numéro.']);
        }

        return ['phone' => $normalized, 'phone_country_id' => $country->id];
    }

    public function resendInvitation(User $actor, User $target): UserSaveResult
    {
        $emailSent = null;
        $user = $this->mutations->run(
            actor: $actor,
            ability: 'resendInvitation',
            loadTarget: fn () => \App\Models\User::whereKey($target->id)->lockForUpdate()->firstOrFail(),
            mutation: function (User $target) use ($actor, &$emailSent): User {
                if ($target->hasVerifiedEmail()) {
                    throw ValidationException::withMessages(['invitation' => 'Ce compte a déjà vérifié son adresse e-mail.']);
                }
                $token = Password::broker('central')->createToken($target);
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
            loadTarget: fn () => User::whereKey($target->id)->lockForUpdate()->firstOrFail(),
            mutation: function (User $target) use ($actor, $status): void {
                if (! in_array($status, [StatusEnum::ACTIVE, StatusEnum::SUSPENDED], true)
                    || ($status === StatusEnum::ACTIVE && ! in_array($target->status, [StatusEnum::SUSPENDED, StatusEnum::INACTIVE], true))) {
                    throw ValidationException::withMessages(['status' => 'Cette transition de statut n’est pas disponible.']);
                }
                $target->status = $status === StatusEnum::ACTIVE && ! $this->access->isStaff($target)
                    && $target->legal_verification_status !== VerificationStatusEnum::VERIFIED
                    ? StatusEnum::PENDING : $status;
                $target->save();
                if ($status === StatusEnum::SUSPENDED) {
                    $this->invalidateAccountAccess($target);
                }
                activity()->causedBy($actor)->performedOn($target)->event('central_account_status_updated')
                    ->withProperties(['status' => $target->status->value, 'requested_status' => $status->value])->log('État du compte central modifié.');
            },
        );
    }

    public function archive(User $actor, User $target, bool $staffOnly = false): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: $staffOnly ? 'archiveStaff' : 'archive',
            loadTarget: fn () => User::whereKey($target->id)->lockForUpdate()->firstOrFail(),
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

    private function invalidateAccountAccess(User $target): void
    {
        $target->forceFill(['remember_token' => null])->save();
        Password::broker('central')->deleteToken($target);
        $target->getConnection()->table('sessions')->where('user_id', $target->id)->delete();
    }

    /** @param list<string> $roleUuids */
    public function assignRoles(User $actor, User $target, array $roleUuids): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: 'assignRoles',
            loadTarget: fn () => User::whereKey($target->id)->lockForUpdate()->firstOrFail(),
            mutation: function (User $target) use ($actor, $roleUuids): void {
                $before = $this->roleAssignments($target);
                $roles = $this->delegableRoles($actor, $roleUuids);
                // The form only edits delegable roles. Never revoke an assignment it cannot display.
                $editableUuids = $this->availableRoles($actor)->pluck('uuid');
                $preserved = $target->roles()->with('permissionComposition')->get()
                    ->reject(fn (Role $role): bool => $editableUuids->contains($role->uuid));
                $roles = $roles->merge($preserved)->unique('id');
                $revokedPermissions = $roles->isEmpty() ? $target->permissions()->pluck('uuid')->all() : [];
                $this->applyRoles($target, $roles);
                if ($revokedPermissions !== []) {
                    $target->syncPermissions([]);
                }
                activity()->causedBy($actor)->performedOn($target)->event('central_roles_updated')
                    ->withProperties(['role_uuids' => $roles->pluck('uuid')->all(), 'revoked_permission_uuids' => $revokedPermissions])
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

    /** @return list<array{role_uuid: string, assigned_at: string}> */
    private function roleAssignments(User $target): array
    {
        return $target->roles()->orderBy('roles.id')->get()->map(fn ($role): array => [
            'role_uuid' => $role->uuid, 'assigned_at' => $role->pivot->assigned_at->toIso8601String(),
        ])->all();
    }

    /** @return Collection<int, Role> */
    public function availableRoles(User $actor): Collection
    {
        return Role::forCentralGuard()->where('is_super_admin', false)->with('permissionComposition')
            ->orderBy('name')->get()->filter(fn (Role $role): bool => Gate::forUser($actor)->allows('assign', $role)
                && $role->permissionComposition->isNotEmpty()
                && $role->permissionComposition->every(fn ($permission): bool => array_key_exists($permission->name, CentralRoleCatalog::PERMISSIONS)))
            ->values();
    }

    /** @param list<string> $uuids
     * @return Collection<int, Role>
     */
    private function delegableRoles(User $actor, array $uuids): Collection
    {
        $roles = Role::forCentralGuard()->whereIn('uuid', $uuids)
            ->orderBy('id')->lockForUpdate()->get();
        if ($roles->count() !== count(array_unique($uuids))) {
            throw ValidationException::withMessages(['roles' => 'Un rôle sélectionné n’est plus disponible.']);
        }
        $permissionIds = [];
        foreach ($roles as $role) {
            Gate::forUser($actor)->authorize('assign', $role);
            $permissions = $role->permissionComposition()->get();
            if ($role->getAttribute('is_super_admin') || $permissions->isEmpty()) {
                throw ValidationException::withMessages(['roles' => 'Le rôle Super Admin et les rôles vides ne sont pas attribuables ici.']);
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
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['roles' => 'Ces rôles recoupent une attribution existante.']);
        }
    }
}
