<?php

namespace App\Services\Central\Countries;

use App\Models\Central\Countries\Country;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class CountryService
{
    private const string EVENT_SAVED = 'country_saved';

    private const string EVENT_ACTIVATION = 'country_activation_updated';

    private const string EVENT_DELETED = 'country_deleted';

    /** Champs écrits par save() : is_active n'en fait jamais partie (il passe par setActive()). */
    private const array EDITABLE = ['code', 'name_fr', 'name_en', 'name_ar'];

    private const array AUDITED = ['code', 'name_fr', 'name_en', 'name_ar', 'is_active'];

    public function __construct(private AuthorizationMutation $mutations) {}

    /** @param array{code: string, name_fr: string, name_en: string, name_ar?: string|null} $data */
    public function save(User $actor, array $data, ?Country $country = null): Country
    {
        return $this->mutations->run(
            actor: $actor,
            ability: $country === null ? 'create' : 'update',
            // Pour une création, la cible est la classe : la policy reçoit Country::class.
            loadTarget: fn () => $country === null ? Country::class : $this->lock($country),
            mutation: function (Country|string $target) use ($actor, $data): Country {
                $country = $target instanceof Country ? $target : null;
                $data = array_intersect_key($data, array_flip(self::EDITABLE));

                if ($country !== null && $country->code !== $data['code'] && $this->isReferenced($country)) {
                    throw ValidationException::withMessages(['code' => 'Le code d’un pays utilisé ne peut plus changer.']);
                }

                $country ??= new Country;
                $before = $country->exists ? $country->only(self::AUDITED) : null;
                $country->fill($data);

                if (! $country->exists) {
                    $country->is_active = false;
                }

                try {
                    $country->save();
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['code' => 'Ce code pays est déjà utilisé.']);
                }

                activity()->causedBy($actor)->performedOn($country)->event(self::EVENT_SAVED)
                    ->withProperties(['before' => $before, 'after' => $country->only(self::AUDITED)])
                    ->log('Pays enregistré.');

                return $country;
            },
        );
    }

    public function setActive(User $actor, Country $country, bool $active): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: 'activate',
            loadTarget: fn () => $this->lock($country),
            mutation: function (Country $country) use ($actor, $active): void {
                if ($country->is_active === $active) {
                    return;
                }

                $country->is_active = $active;
                $country->save();

                activity()->causedBy($actor)->performedOn($country)->event(self::EVENT_ACTIVATION)
                    ->withProperties(['is_active' => $active])->log('Disponibilité du pays modifiée.');
            },
        );
    }

    public function delete(User $actor, Country $country): void
    {
        $this->mutations->run(
            actor: $actor,
            ability: 'delete',
            loadTarget: fn () => $this->lock($country),
            mutation: function (Country $country) use ($actor): void {
                if ($this->isReferenced($country)) {
                    throw ValidationException::withMessages(['country' => 'Ce pays est utilisé par des comptes ou des zones. Désactivez-le au lieu de le retirer.']);
                }

                $country->forceFill(['is_active' => false])->save();
                $country->delete();

                activity()->causedBy($actor)->performedOn($country)->event(self::EVENT_DELETED)
                    ->withProperties(['code' => $country->code])->log('Pays retiré du catalogue.');
            },
        );
    }

    private function lock(Country $country): Country
    {
        return Country::whereKey($country->id)->lockForUpdate()->firstOrFail();
    }

    private function isReferenced(Country $country): bool
    {
        $connection = $country->getConnection();

        return User::withTrashed()->where('country_id', $country->id)->exists()
            || ($connection->getSchemaBuilder()->hasColumn('users', 'phone_country_id')
                && User::withTrashed()->where('phone_country_id', $country->id)->exists())
            || $connection->table('geographic_areas')->where('country_id', $country->id)->exists();
    }
}
