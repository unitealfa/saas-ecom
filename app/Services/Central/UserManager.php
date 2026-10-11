<?php

namespace App\Services\Central;

use App\DTOs\Central\UserData;
use App\DTOs\Central\UserProfileData;
use App\DTOs\Central\UserSaveResult;
use App\Enums\VerificationStatusEnum;
use App\Enums\Users\StatusEnum;
use App\Models\Central\Country;
use App\Models\Central\Role;
use App\Models\Central\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class UserManager
{
    public function __construct(
        private AuthorizationMutation $mutations,
        private DefaultSubscription $subscriptions,
        private CentralUserAccess $access,
        private AccountMailDelivery $mail,
    ) {}

    public function create(User $actor, UserData $data): UserSaveResult
    {
        $emailSent = null;
        $user = $this->mutations->run(function () use ($actor, $data, &$emailSent): User {
            Gate::forUser($actor)->authorize($data->staff ? 'createStaff' : 'create', User::class);
            if (! $data->staff && $data->roleUuids !== []) {
                throw ValidationException::withMessages(['roles' => 'Un compte commerçant est créé sans rôle SaaS.']);
            }
            $roles = $data->staff ? $this->delegableRoles($actor, $data->roleUuids) : collect();
            if ($data->staff && $roles->isEmpty()) {
                throw ValidationException::withMessages(['roles' => 'Sélectionnez au moins un rôle pour le personnel SaaS.']);
            }
            $country = Country::where('uuid', $data->countryUuid)->where('is_active', true)->lockForUpdate()->firstOrFail();
            $user = new \App\Models\User([
                'last_name' => $data->lastName, 'first_name' => $data->firstName,
                'email' => $data->email, 'password' => Str::random(64),
            ]);
            $user->country_id = $country->id;
            $user->save();
            $this->subscriptions->ensure($user, $actor);
            $this->applyRoles($user, $roles);
            $token = Password::broker('central')->createToken($user);
            $user->getConnection()->afterCommit(function () use ($user, $token, &$emailSent): void {
                $emailSent = $this->mail->invitation($user, $token);
            });
            activity()->causedBy($actor)->performedOn($user)->event('account_invited')
                ->withProperties(['staff' => $data->staff, 'role_uuids' => $roles->pluck('uuid')->all()])
                ->log('Compte central créé et invité à choisir son mot de passe.');

            return $user;
        });

        return new UserSaveResult($user, $emailSent);
    }

    public function update(User $actor, User $target, UserProfileData $data): UserSaveResult
    {
        $emailSent = null;
        $user = $this->mutations->run(function () use ($actor, $target, $data, &$emailSent): User {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $target);
            $country = Country::where('uuid', $data->countryUuid)
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $target->country_id))->lockForUpdate()->firstOrFail();
            $emailChanged = $target->email !== $data->email;
            $wasVerified = $target->hasVerifiedEmail();
            if ($emailChanged) {
                Password::broker('central')->deleteToken($target);
            }
            $target->forceFill([
                'first_name' => $data->firstName, 'last_name' => $data->lastName,
                'email' => $data->email, 'phone' => $data->phone, 'country_id' => $country->id,
            ]);
            $attributes = array_intersect_key($data->attributes, array_flip(['locale', ...UserProfileData::PROFESSIONAL_FIELDS]));
            if ($this->access->isStaff($target) && array_intersect_key($attributes, array_flip(UserProfileData::PROFESSIONAL_FIELDS)) !== []) {
                throw ValidationException::withMessages(['legal_form' => 'Le personnel SaaS ne possède pas de profil professionnel vendeur.']);
            }
            $target->forceFill($attributes);
            if ($emailChanged) {
                $target->email_verified_at = null;
            }
            if ($target->isDirty('phone')) {
                $target->forceFill(['phone_verified_at' => null, 'whatsapp_verified_at' => null]);
            }
            $hasProfessionalProfile = collect(UserProfileData::PROFESSIONAL_FIELDS)
                ->contains(fn (string $field): bool => $target->getAttribute($field) !== null && $target->getAttribute($field) !== '');
            if ($target->isDirty(['first_name', 'last_name', 'email', 'phone', 'country_id', ...UserProfileData::PROFESSIONAL_FIELDS])
                && ($target->legal_profile_version !== null || $hasProfessionalProfile)) {
                $professionalChanged = $target->isDirty(UserProfileData::PROFESSIONAL_FIELDS);
                $complete = ! $professionalChanged && $target->legal_profile_version !== null
                    ? $target->legal_verification_status !== VerificationStatusEnum::INCOMPLETE
                    : filled($target->legal_form) && filled($target->activity_nature) && filled($target->legal_address)
                        && (filled($target->getAttribute('registration_number')) || filled($target->getAttribute('artisan_card_number')));
                $target->forceFill([
                    'legal_profile_version' => ($target->legal_profile_version ?? 0) + 1,
                    'legal_verification_status' => $complete ? VerificationStatusEnum::PENDING : VerificationStatusEnum::INCOMPLETE,
                    'legal_verified_at' => null, 'legal_verified_by_id' => null,
                ]);
            }
            $fields = array_keys($target->getDirty());
            $target->save();
            if ($emailChanged) {
                $token = $wasVerified ? null : Password::broker('central')->createToken($target);
                $target->getConnection()->afterCommit(function () use ($target, $token, &$emailSent): void {
                    $emailSent = $token === null ? $this->mail->verification($target) : $this->mail->invitation($target, $token);
                });
            }
            activity()->causedBy($actor)->performedOn($target)->event('central_profile_updated')
                ->withProperties(['changed_fields' => $fields])->log('Informations du compte central modifiées.');

            return $target;
        });

        return new UserSaveResult($user, $emailSent);
    }

    public function resendInvitation(User $actor, User $target): UserSaveResult
    {
        $emailSent = null;
        $user = $this->mutations->run(function () use ($actor, $target, &$emailSent): User {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $target);
            if ($target->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['invitation' => 'Ce compte a déjà vérifié son adresse e-mail.']);
            }
            $token = Password::broker('central')->createToken($target);
            $target->getConnection()->afterCommit(function () use ($target, $token, &$emailSent): void {
                $emailSent = $this->mail->invitation($target, $token);
            });
            activity()->causedBy($actor)->performedOn($target)->event('account_invitation_requested')
                ->log('Renvoi de l’invitation centrale demandé.');

            return $target;
        });

        return new UserSaveResult($user, $emailSent);
    }

    public function changeStatus(User $actor, User $target, StatusEnum $status): void
    {
        $this->mutations->run(function () use ($actor, $target, $status): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $action = $status === StatusEnum::SUSPENDED ? 'suspend' : 'restore';
            Gate::forUser($actor)->authorize($action, $target);
            if (! in_array($status, [StatusEnum::ACTIVE, StatusEnum::SUSPENDED], true)
                || ($status === StatusEnum::ACTIVE && ! in_array($target->status, [StatusEnum::SUSPENDED, StatusEnum::INACTIVE], true))) {
                throw ValidationException::withMessages(['status' => 'Cette transition de statut n’est pas disponible.']);
            }
            $target->status = $status;
            $target->save();
            if ($status === StatusEnum::SUSPENDED) {
                $this->invalidateAccountAccess($target);
            }
            activity()->causedBy($actor)->performedOn($target)->event('central_account_status_updated')
                ->withProperties(['status' => $status->value])->log('État du compte central modifié.');
        });
    }

    public function delete(User $actor, User $target): void
    {
        $this->mutations->run(function () use ($actor, $target): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $target);
            $target->status = StatusEnum::DELETED;
            $target->save();
            $this->invalidateAccountAccess($target);
            $target->delete();
            activity()->causedBy($actor)->performedOn($target)->event('central_account_deleted')
                ->log('Compte central supprimé logiquement ; données et boutiques conservées.');
        });
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
        $this->mutations->run(function () use ($actor, $target, $roleUuids): void {
            $roles = $this->delegableRoles($actor, $roleUuids);
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('assignRoles', $target);
            $this->applyRoles($target, $roles);
            activity()->causedBy($actor)->performedOn($target)->event('central_roles_updated')
                ->withProperties(['role_uuids' => $roles->pluck('uuid')->all()])
                ->log('Rôles centraux attribués via Spatie.');
        });
    }

    /** @return Collection<int, Role> */
    public function availableRoles(User $actor): Collection
    {
        return Role::where('guard_name', 'central')->where('is_super_admin', false)->with('permissions')
            ->orderBy('name')->get()->filter(fn (Role $role): bool => $this->access->mayDelegateRole($actor, $role)
                && $role->permissions->isNotEmpty()
                && $role->permissions->every(fn ($permission): bool => array_key_exists($permission->name, CentralRoleCatalog::PERMISSIONS)
                    && $this->access->allows($actor, $permission->name)))
            ->values();
    }

    /** @param list<string> $uuids
     * @return Collection<int, Role>
     */
    private function delegableRoles(User $actor, array $uuids): Collection
    {
        $roles = Role::where('guard_name', 'central')->whereIn('uuid', $uuids)
            ->orderBy('id')->lockForUpdate()->get();
        if ($roles->count() !== count(array_unique($uuids))) {
            throw ValidationException::withMessages(['roles' => 'Un rôle sélectionné n’est plus disponible.']);
        }
        $permissionIds = [];
        foreach ($roles as $role) {
            abort_unless($this->access->mayDelegateRole($actor, $role), 403);
            $permissions = $role->permissions()->get();
            if ($role->getAttribute('is_super_admin') || $permissions->isEmpty()) {
                throw ValidationException::withMessages(['roles' => 'Le rôle Super Admin et les rôles vides ne sont pas attribuables ici.']);
            }
            foreach ($permissions as $permission) {
                abort_unless(array_key_exists($permission->name, CentralRoleCatalog::PERMISSIONS)
                    && $this->access->allows($actor, $permission->name), 403);
                if (in_array($permission->getKey(), $permissionIds, true)) {
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
