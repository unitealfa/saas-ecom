<flux:input name="first_name" label="Prénom" :value="old('first_name', $user?->first_name)" autocomplete="given-name" />
<flux:input name="last_name" label="Nom de famille" :value="old('last_name', $user?->last_name)" autocomplete="family-name" required />
<flux:input name="email" label="E-mail" type="email" :value="old('email', $user?->email)" autocomplete="email" required />
<flux:input name="phone" label="Téléphone" type="tel" :value="old('phone', $user?->phone)" autocomplete="tel" />
<flux:select name="phone_country_uuid" label="Pays et indicatif du téléphone">
    <option value="">{{ $user ? 'Conserver le pays du numéro actuel' : 'Même pays que le profil' }}</option>
    @foreach (($phoneCountries ?? $countries) as $country)
        <option value="{{ $country->uuid }}" @selected(old('phone_country_uuid', $user?->phoneCountry?->uuid) === $country->uuid)>{{ $country->name_fr }} ({{ $country->calling_code }}){{ $country->is_active ? '' : ' — pays inactif, numéro existant' }}</option>
    @endforeach
</flux:select>
<flux:select name="country_uuid" label="Pays" required>
    <option value="">Choisir un pays</option>
    @foreach ($countries as $country)
        <option value="{{ $country->uuid }}" @selected(old('country_uuid', $user?->country?->uuid) === $country->uuid)>{{ $country->name_fr }}</option>
    @endforeach
</flux:select>
@if ($user !== null)
    <flux:input name="locale" label="Langue du compte (fr, en, ar…)" :value="old('locale', $user->getAttribute('locale'))" required />
@endif
