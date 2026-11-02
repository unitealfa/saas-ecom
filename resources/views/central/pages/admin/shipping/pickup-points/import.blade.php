<x-layouts::app title="Importer les bureaux officiels">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Importer les bureaux officiels</flux:heading>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <flux:text>CSV UTF-8, virgule ou point-virgule, 1 Mo et 500 lignes maximum. Utilisez un export officiel contrôlé. Les nouveaux bureaux seront inactifs ; la disponibilité des bureaux existants restera inchangée. Aucune restauration d’archive.</flux:text>
        <flux:text>Colonnes dans cet ordre : {{ implode(',', \App\Services\Central\Shipping\PickupPointImportService::HEADERS) }}. municipality_uuid, phone et map_url peuvent être vides. Les zones sont identifiées par leurs UUID Aydra, consultables dans Géographie. Le code du bureau doit venir du transporteur.</flux:text>
        <form method="POST" action="{{ route('central.pickup-points.preview') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf
            <flux:select name="carrier_uuid" label="Transporteur" required>
                <option value="">Sélectionner un transporteur</option>
                @foreach ($carriers as $carrier)<option value="{{ $carrier->uuid }}" @selected(old('carrier_uuid', request('carrier_uuid')) === $carrier->uuid)>{{ $carrier->name }}</option>@endforeach
            </flux:select>
            <flux:input name="file" type="file" label="Fichier officiel CSV" accept=".csv" required />
            <flux:button type="submit">Prévisualiser</flux:button>
        </form>
        @if ($preview)
            <flux:heading size="lg">Aperçu — {{ $preview['carrier']->name }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead><tr><th class="p-2">Code</th><th class="p-2">Bureau</th><th class="p-2">Wilaya / commune</th><th class="p-2">Adresse</th><th class="p-2">Téléphone</th><th class="p-2">Carte</th><th class="p-2">Source</th><th class="p-2">Action</th></tr></thead>
                    <tbody>@foreach ($preview['rows'] as $row)
                        <tr><td class="p-2">{{ $row['external_code'] }}</td><td class="p-2">{{ $row['name'] }}</td><td class="p-2">{{ $row['zone_label'] }} / {{ $row['municipality_label'] ?? 'Non précisée' }}</td><td class="p-2">{{ $row['address'] }}</td><td class="p-2">{{ $row['phone'] }}</td><td class="p-2">{{ $row['map_url'] }}</td><td class="p-2">{{ $row['reference_source'] }}</td><td class="p-2">{{ $row['operation'] }}</td></tr>
                    @endforeach</tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('central.pickup-points.confirm') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $preview['token'] }}">
                <flux:text>L’aperçu expire après quinze minutes. Confirmez seulement après avoir contrôlé la source, les coordonnées et les rattachements.</flux:text>
                <flux:button type="submit" variant="primary">Confirmer l’import vérifié</flux:button>
            </form>
        @endif
        @can('viewAny', \App\Models\Central\Shipping\PickupPoint::class)<flux:button :href="route('central.pickup-points.index')">Retour aux bureaux</flux:button>@endcan
    </section>
</x-layouts::app>
