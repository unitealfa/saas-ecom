<x-layouts::app title="Importer les codes des transporteurs">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl">Importer les correspondances géographiques</flux:heading>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <flux:text>CSV UTF-8, séparé par virgules ou points-virgules, maximum 500 lignes et 1 Mo. Chaque ligne doit utiliser l’UUID public de la zone Aydra et les vrais codes communs du transporteur, sans exception privée de contrat. Les noms, codes postaux et identifiants MySQL ne sont pas utilisés pour deviner les liens.</flux:text>
        <flux:text>Première ligne obligatoire :</flux:text>
        <code class="overflow-x-auto">{{ implode(',', \App\Services\Central\Shipping\CarrierGeoImportService::HEADERS) }}</code>
        <flux:text>L’aperçu ne modifie aucune correspondance. La confirmation crée ou actualise les correspondances vérifiées selon votre source, sans réactiver les archives ni modifier leurs UUID. Une erreur annule l’ensemble de l’import. Les codes externes identiques sur plusieurs zones sont autorisés.</flux:text>
        <form method="POST" enctype="multipart/form-data" action="{{ route('central.carrier-mappings.preview') }}" class="flex flex-col gap-4">
            @csrf
            <flux:select name="carrier_uuid" label="Transporteur" required>
                <option value="">Sélectionner un transporteur</option>
                @foreach ($carriers as $carrier)<option value="{{ $carrier->uuid }}" @selected(old('carrier_uuid', $preview['carrier']->uuid ?? request('carrier_uuid')) === $carrier->uuid)>{{ $carrier->name }}</option>@endforeach
            </flux:select>
            <flux:input type="file" name="file" label="Fichier CSV" accept=".csv" required />
            <flux:button type="submit">Prévisualiser</flux:button>
        </form>
        @if ($preview !== null)
            <flux:heading>Aperçu — {{ $preview['carrier']->name }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr>@foreach (['Zone Aydra', 'Code externe', 'Nom externe', 'Code wilaya', 'Source', 'Opération'] as $heading)<th class="p-2">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($preview['rows'] as $row)
                            <tr><td class="p-2">{{ $row['zone_label'] }}</td><td class="p-2">{{ $row['external_code'] }}</td><td class="p-2">{{ $row['external_name'] }}</td><td class="p-2">{{ $row['external_province_code'] }}</td><td class="p-2">{{ $row['verification_source'] }}</td><td class="p-2">{{ $row['operation'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('central.carrier-mappings.confirm') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $preview['token'] }}">
                <flux:text>Confirmez uniquement si vous avez vérifié les codes et les rattachements dans la source indiquée. L’aperçu expire après quinze minutes.</flux:text>
                <flux:button type="submit" variant="primary">Confirmer l’import vérifié</flux:button>
            </form>
        @endif
        @can('viewAny', \App\Models\Central\Shipping\CarrierGeoMapping::class)<flux:button :href="route('central.carrier-mappings.index')">Retour aux correspondances</flux:button>@endcan
    </section>
</x-layouts::app>
