@if ($errors->any())
    <flux:callout variant="danger" role="alert">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </flux:callout>
@endif
<form method="POST" action="{{ $area === null ? route('central.geography.store') : route('central.geography.update', $area->uuid) }}" class="flex flex-col gap-5">
    @csrf
    @if ($area !== null)
        @method('PUT')
        <input type="hidden" name="expected_state" value="{{ $area->stateToken() }}">
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt>Pays</dt><dd>{{ $area->country->name_fr }}</dd></div>
            <div><dt>Type</dt><dd>{{ $area->type->label() }}</dd></div>
            <div><dt>Wilaya parente</dt><dd>{{ $area->parent?->name_fr ?? 'Aucune' }}</dd></div>
            <div><dt>Code permanent</dt><dd>{{ $area->code }}</dd></div>
        </dl>
    @else
        <flux:select name="country_uuid" label="Pays" required>
            <option value="">Sélectionner un pays</option>
            @foreach ($countries as $country)
                <option value="{{ $country->uuid }}" @selected(old('country_uuid') === $country->uuid)>{{ $country->name_fr }}</option>
            @endforeach
        </flux:select>
        <flux:select name="type" label="Type de zone" required>
            @foreach (\App\Enums\Central\Geography\GeoZoneTypeEnum::cases() as $type)
                <option value="{{ $type->value }}" @selected((string) old('type', \App\Enums\Central\Geography\GeoZoneTypeEnum::PROVINCE->value) === (string) $type->value)>{{ $type->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select name="parent_uuid" label="Wilaya parente" description="Obligatoire pour une commune, du même pays. Laisser vide pour une wilaya.">
            <option value="">Aucune (wilaya)</option>
            @foreach ($provinces as $province)
                <option value="{{ $province->uuid }}" @selected(old('parent_uuid') === $province->uuid)>{{ $province->code }} — {{ $province->name_fr }}</option>
            @endforeach
        </flux:select>
        <flux:input name="code" label="Code permanent du référentiel" :value="old('code')" description="Exemple : 16 pour une wilaya ; dz-16001 pour une commune, à partir du champ post_code de la source choisie." required />
    @endif
    <flux:input name="name_fr" label="Nom en français" :value="old('name_fr', $area?->name_fr)" required />
    <flux:input name="name_ar" label="Nom en arabe (facultatif)" :value="old('name_ar', $area?->name_ar)" dir="rtl" lang="ar" />
    <flux:input name="reference_source" label="Source du référentiel (facultative)" :value="old('reference_source', $area?->reference_source)" />
    <flux:input name="reference_version" label="Version du référentiel (facultative)" :value="old('reference_version', $area?->reference_version)" />
    <flux:input name="effective_at" type="date" label="Date de prise d’effet (facultative)" :value="old('effective_at', $area?->effective_at?->format('Y-m-d'))" description="Indiquer une date d’effet vérifiée, distincte de la date de téléchargement ou de publication. Laisser vide si elle est inconnue." />
    <div class="flex flex-wrap gap-3">
        <flux:button type="submit" variant="primary">Enregistrer</flux:button>
        @can('viewAny', \App\Models\Central\Geography\GeographicArea::class)
            <flux:button :href="route('central.geography.index')">Retour au référentiel</flux:button>
        @endcan
    </div>
</form>
