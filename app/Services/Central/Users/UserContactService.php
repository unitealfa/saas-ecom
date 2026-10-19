<?php

namespace App\Services\Central\Users;

use App\DTOs\Central\Users\UserProfileData;
use App\Models\Central\Country;
use App\Models\Central\User;
use App\Models\Central\UserContact;
use App\Notifications\Central\Auth\VerifyContactEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class UserContactService
{
    public function __construct(private PhoneNumberNormalizer $phones) {}

    public function authorize(User $actor, User $target): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Les coordonnées centrales exigent le contexte central.');
        }
        Gate::forUser($actor)->authorize($actor->is($target) ? 'updateOwnProfile' : 'update', $target);
    }

    public function add(User $actor, User $target, string $kind, string $value, ?string $countryUuid = null): UserContact
    {
        $this->authorize($actor, $target);
        $this->requireRegistry($target);
        $country = null;
        if ($kind === 'email') {
            $value = strtolower(trim($value));
            validator(['value' => $value], ['value' => ['required', 'email', 'max:255']])->validate();
        } elseif ($kind === 'phone') {
            $country = Country::where('uuid', $countryUuid)->where('is_active', true)->first();
            if ($country === null) {
                throw ValidationException::withMessages(['country_uuid' => 'Choisissez un pays actif pour ce numéro.']);
            }
            $value = $this->phones->normalize($value, $country->code, 'value');
        } else {
            throw ValidationException::withMessages(['kind' => 'Type de coordonnée invalide.']);
        }
        try {
            return $target->getConnection()->transaction(function () use ($actor, $target, $kind, $value, $country): UserContact {
                $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
                $this->authorize($actor, $target);
                if ($country !== null && ! Country::whereKey($country->id)->where('is_active', true)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['country_uuid' => 'Ce pays n’est plus disponible.']);
                }
                if (UserContact::where('kind', $kind)->where('value', $value)->exists()) {
                    $this->conflict($kind);
                }
                $contact = UserContact::create([
                    'user_id' => $target->id, 'kind' => $kind, 'value' => $value,
                    'country_id' => $country?->id, 'is_primary' => false,
                ]);
                if ($kind === 'phone' && $target->phone === null) {
                    $this->makePrimary($actor, $target, $contact);
                    $contact->refresh();
                }
                activity()->causedBy($actor)->performedOn($target)->event('contact_added')
                    ->withProperties(['contact_uuid' => $contact->uuid, 'kind' => $kind])->log('Coordonnée ajoutée.');

                return $contact;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->conflict($kind);
        }
    }

    public function makePrimary(User $actor, User $target, UserContact $contact): void
    {
        $this->authorize($actor, $target);
        $this->requireRegistry($target);
        $target->getConnection()->transaction(function () use ($actor, $target, $contact): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $target);
            $contact = $target->contacts()->whereKey($contact->id)->lockForUpdate()->firstOrFail();
            if ($contact->is_primary) {
                return;
            }
            if ($contact->kind === 'email' && $contact->verified_at === null) {
                throw ValidationException::withMessages(['value' => 'Vérifiez cette adresse avant de la choisir comme principale.']);
            }
            $data = new UserProfileData(
                $target->last_name, $target->first_name,
                $contact->kind === 'email' ? $contact->value : $target->email,
                $contact->kind === 'phone' ? $contact->value : $target->phone,
                $target->country->uuid, [],
                $contact->kind === 'phone' ? $contact->country->uuid : $target->phoneCountry?->uuid,
            );
            app(UserManagementService::class)->update($actor, $target, $data,
                $actor->is($target) ? 'updateOwnProfile' : 'update', notifyEmailChange: false);
            $target->refresh()->forceFill([$contact->kind.'_verified_at' => $contact->verified_at])->save();
            activity()->causedBy($actor)->performedOn($target)->event('contact_primary_changed')
                ->withProperties(['contact_uuid' => $contact->uuid])->log('Coordonnée principale modifiée.');
        }, 3);
    }

    public function remove(User $actor, User $target, UserContact $contact): void
    {
        $this->authorize($actor, $target);
        $this->requireRegistry($target);
        $target->getConnection()->transaction(function () use ($actor, $target, $contact): void {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $target);
            $contact = $target->contacts()->whereKey($contact->id)->lockForUpdate()->firstOrFail();
            if ($contact->is_primary) {
                throw ValidationException::withMessages(['value' => 'Choisissez une autre coordonnée principale avant de supprimer celle-ci.']);
            }
            if ($contact->kind === 'email') {
                $target->getConnection()->table('password_reset_tokens')->where('email', $contact->value)->delete();
            }
            activity()->causedBy($actor)->performedOn($target)->event('contact_removed')
                ->withProperties(['contact_uuid' => $contact->uuid, 'kind' => $contact->kind])->log('Coordonnée secondaire supprimée.');
            $contact->delete();
        }, 3);
    }

    public function sendVerification(User $actor, User $target, UserContact $contact): bool
    {
        $this->authorize($actor, $target);
        abort_unless($contact->user_id === $target->id && $contact->kind === 'email', 404);
        if ($contact->verified_at !== null) {
            return true;
        }
        if ($contact->is_primary) {
            return app(\App\Services\Central\Auth\AccountMailDelivery::class)->verification($target);
        }
        try {
            \Illuminate\Support\Facades\Notification::route('mail', $contact->value)
                ->notify(new VerifyContactEmail($contact));

            return true;
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $exception) {
            \Illuminate\Support\Facades\Log::warning('Contact verification delivery failed.', ['exception_class' => $exception::class]);

            return false;
        }
    }

    public function verify(User $actor, UserContact $contact, string $hash): void
    {
        abort_unless(! tenancy()->initialized && $actor->id === $contact->user_id, 403);
        $actor->getConnection()->transaction(function () use ($actor, $contact, $hash): void {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $contact = $user->contacts()->whereKey($contact->id)->lockForUpdate()->firstOrFail();
            abort_unless($contact->kind === 'email' && hash_equals(sha1($contact->value), $hash), 403);
            if ($contact->verified_at !== null) {
                return;
            }
            $contact->forceFill(['verified_at' => now()])->save();
            if ($contact->is_primary) {
                $user->forceFill(['email_verified_at' => $contact->verified_at])->save();
                $user->getConnection()->afterCommit(fn () => event(new \Illuminate\Auth\Events\Verified($user)));
            }
            activity()->causedBy($actor)->performedOn($user)->event('contact_email_verified')
                ->withProperties(['contact_uuid' => $contact->uuid])->log('Adresse e-mail confirmée.');
        }, 3);
    }

    private function conflict(string $kind): never
    {
        throw ValidationException::withMessages(['value' => $kind === 'email'
            ? 'Cette adresse e-mail est déjà utilisée.' : 'Ce numéro de téléphone est déjà associé à un compte.']);
    }

    private function requireRegistry(User $target): void
    {
        if (! $target->getConnection()->getSchemaBuilder()->hasTable('user_contacts')) {
            throw ValidationException::withMessages(['value' => 'La gestion des coordonnées attend la correction des conflits et la migration centrale.']);
        }
    }
}
