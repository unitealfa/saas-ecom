<x-layouts::app title="Importer les bureaux officiels">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Importer les bureaux officiels</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Prévisualisez un export officiel avant de confirmer son import.</p></div>@can('viewAny', \App\Models\Central\Shipping\PickupPoint::class)<flux:button :href="route('central.pickup-points.index')" icon="arrow-left">Retour aux bureaux</flux:button>@endcan</header>
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.pickup-points.preview') }}" enctype="multipart/form-data" class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6">
            @csrf
            <div><h2 class="aydra-heading text-lg font-semibold">Fichier officiel</h2><p class="aydra-muted mt-2 max-w-3xl text-sm">CSV UTF-8, virgule ou point-virgule, 1 Mo et 500 lignes maximum. Utilisez un export officiel contrôlé. Les nouveaux bureaux seront inactifs ; la disponibilité des bureaux existants restera inchangée. Aucune restauration d’archive.</p><p class="aydra-muted mt-2 break-words text-sm">Colonnes dans cet ordre : {{ implode(',', \App\Services\Central\Shipping\PickupPointImportService::HEADERS) }}. municipality_uuid, phone et map_url peuvent être vides. Les zones sont identifiées par leurs UUID Aydra, consultables dans Géographie. Le code du bureau doit venir du transporteur.</p></div>
            <flux:select name="carrier_uuid" label="Transporteur" required>
                <option value="">Sélectionner un transporteur</option>
                @foreach ($carriers as $carrier)<option value="{{ $carrier->uuid }}" @selected(old('carrier_uuid', request('carrier_uuid')) === $carrier->uuid)>{{ $carrier->name }}</option>@endforeach
            </flux:select>
            <flux:input name="file" type="file" label="Fichier officiel CSV" accept=".csv" required />
            <flux:button type="submit" variant="primary" class="self-start">Prévisualiser</flux:button>
        </form>
        @if ($preview)
            <section class="aydra-users-panel"><div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Aperçu — {{ $preview['carrier']->name }}</h2><p class="aydra-muted mt-1 text-sm">Vérifiez les informations avant de confirmer.</p></div></div><div class="aydra-users-table"><flux:table class="whitespace-normal"><flux:table.columns>@foreach (['Code', 'Bureau', 'Wilaya / commune', 'Adresse', 'Téléphone', 'Carte', 'Source', 'Action'] as $heading)<flux:table.column>{{ $heading }}</flux:table.column>@endforeach</flux:table.columns><flux:table.rows>@foreach ($preview['rows'] as $row)
                <flux:table.row><flux:table.cell>{{ $row['external_code'] }}</flux:table.cell><flux:table.cell variant="strong">{{ $row['name'] }}</flux:table.cell><flux:table.cell>{{ $row['zone_label'] }} / {{ $row['municipality_label'] ?? 'Non précisée' }}</flux:table.cell><flux:table.cell>{{ $row['address'] }}</flux:table.cell><flux:table.cell>{{ $row['phone'] }}</flux:table.cell><flux:table.cell>{{ $row['map_url'] }}</flux:table.cell><flux:table.cell>{{ $row['reference_source'] }}</flux:table.cell><flux:table.cell>{{ $row['operation'] }}</flux:table.cell></flux:table.row>
            @endforeach</flux:table.rows></flux:table></div></section>
            <form method="POST" action="{{ route('central.pickup-points.confirm') }}" class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6">
                @csrf
                <input type="hidden" name="token" value="{{ $preview['token'] }}">
                <flux:text>L’aperçu expire après quinze minutes. Confirmez seulement après avoir contrôlé la source, les coordonnées et les rattachements.</flux:text>
                <flux:button type="submit" variant="primary" class="self-start">Confirmer l’import vérifié</flux:button>
            </form>
        @endif
    </section>
</x-layouts::app>
