<?php

namespace App\Services\Central\Countries;

use App\Models\Central\Country;
use App\Models\Central\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CountryService
{
    public function __construct(private AuthorizationMutation $mutations) {}

    /** @param array{code: string, name_fr: string, name_en: string, name_ar?: string|null} $data */
    public function save(User $actor, array $data, ?Country $country = null): Country
    {
        return $this->mutations->run(function () use ($actor, $data, $country): Country {
            $country = $country === null ? null : Country::whereKey($country->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($country === null ? 'create' : 'update', $country ?? Country::class);
            if ($country !== null && $country->code !== $data['code'] && $this->isReferenced($country)) {
                throw ValidationException::withMessages(['code' => 'Le code d’un pays utilisé ne peut plus changer.']);
            }
            $country ??= new Country;
            $before = $country->exists ? $country->only(['code', 'name_fr', 'name_en', 'name_ar', 'is_active']) : null;
            $country->fill($data);
            if (! $country->exists) {
                $country->is_active = false;
            }
            $country->save();
            activity()->causedBy($actor)->performedOn($country)->event('country_saved')
                ->withProperties(['before' => $before, 'after' => $country->only(['code', 'name_fr', 'name_en', 'name_ar', 'is_active'])])
                ->log('Pays enregistré.');

            return $country;
        });
    }

    public function setActive(User $actor, Country $country, bool $active): void
    {
        $this->mutations->run(function () use ($actor, $country, $active): void {
            $country = Country::whereKey($country->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('activate', $country);
            $country->is_active = $active;
            $country->save();
            activity()->causedBy($actor)->performedOn($country)->event('country_activation_updated')
                ->withProperties(['is_active' => $active])->log('Disponibilité du pays modifiée.');
        });
    }

    public function delete(User $actor, Country $country): void
    {
        $this->mutations->run(function () use ($actor, $country): void {
            $country = Country::whereKey($country->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $country);
            if ($this->isReferenced($country)) {
                throw ValidationException::withMessages(['country' => 'Ce pays est utilisé par des comptes ou des zones. Désactivez-le au lieu de le retirer.']);
            }
            $country->forceFill(['is_active' => false])->save();
            $country->delete();
            activity()->causedBy($actor)->performedOn($country)->event('country_deleted')->log('Pays retiré du catalogue.');
        });
    }

    private function isReferenced(Country $country): bool
    {
        return User::withTrashed()->where('country_id', $country->id)->exists()
            || ($country->getConnection()->getSchemaBuilder()->hasColumn('users', 'phone_country_id')
                && User::withTrashed()->where('phone_country_id', $country->id)->exists())
            || ($country->getConnection()->getSchemaBuilder()->hasTable('user_contacts')
                && \App\Models\Central\UserContact::where('country_id', $country->id)->exists())
            || $country->getConnection()->table('geographic_areas')->where('country_id', $country->id)->exists();
    }
}
