<x-layouts::app title="Correspondances géographiques">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Correspondances géographiques des transporteurs</flux:heading>
        <flux:text>Associez une wilaya ou une commune d’Aydra aux codes réellement fournis par le transporteur. Aucun code postal ou identifiant interne n’est converti automatiquement en code transporteur.</flux:text>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <div class="flex flex-wrap gap-3">
            @can('create', \App\Models\Central\Shipping\CarrierGeoMapping::class)
                <flux:button :href="route('central.carrier-mappings.create', ['carrier_uuid' => request('carrier_uuid')])" variant="primary">Créer une correspondance</flux:button>
            @endcan
            @can('import', \App\Models\Central\Shipping\CarrierGeoMapping::class)
                <flux:button :href="route('central.carrier-mappings.import', ['carrier_uuid' => request('carrier_uuid')])">Importer un CSV</flux:button>
            @endcan
        </div>
        <form method="GET" action="{{ route('central.carrier-mappings.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:select name="carrier_uuid" label="Transporteur">
                <option value="">Tous</option>
                @foreach ($carriers as $carrier)<option value="{{ $carrier->uuid }}" @selected(request('carrier_uuid') === $carrier->uuid)>{{ $carrier->name }}</option>@endforeach
            </flux:select>
            <flux:input name="search" label="Rechercher" :value="request('search')" placeholder="Zone, code ou nom externe" />
            <flux:select name="is_active" label="Disponibilité">
                <option value="">Toutes</option>
                <option value="1" @selected(request('is_active') === '1')>Actives</option>
                <option value="0" @selected(request('is_active') === '0')>Inactives / archivées</option>
            </flux:select>
            <flux:button type="submit">Filtrer</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr>@foreach (['Transporteur', 'Zone Aydra', 'Code externe', 'Nom externe', 'Code wilaya externe', 'Source / version', 'État', 'Actions'] as $heading)<th class="p-3">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($mappings as $mapping)
                        <tr>
                            <td class="p-3">{{ $mapping->carrier->name }}</td>
                            <td class="p-3">{{ $mapping->area->name_fr }}<div>{{ $mapping->zone_type->label() }} — {{ $mapping->area->code }}</div></td>
                            <td class="p-3">{{ $mapping->external_code }}</td>
                            <td class="p-3">{{ $mapping->external_name }}</td>
                            <td class="p-3">{{ $mapping->external_province_code }}</td>
                            <td class="p-3">{{ $mapping->verification_source }}<div>Version {{ $mapping->mapping_version }} — {{ $mapping->verified_at?->format('d/m/Y H:i') ?? 'Non vérifiée' }}</div></td>
                            <td class="p-3">{{ $mapping->is_active ? 'Active' : 'Inactive / archivée' }}</td>
                            <td class="p-3"><div class="flex flex-wrap gap-2">
                                @can('update', $mapping)<flux:button :href="route('central.carrier-mappings.edit', $mapping)">Modifier</flux:button>@endcan
                                @can('archive', $mapping)
                                    <flux:modal.trigger :name="'archive-mapping-'.$mapping->uuid"><flux:button variant="danger">Archiver</flux:button></flux:modal.trigger>
                                    <flux:modal :name="'archive-mapping-'.$mapping->uuid">
                                        <form method="POST" action="{{ route('central.carrier-mappings.archive', $mapping) }}" class="space-y-4">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="expected_state" value="{{ $mapping->managementState() }}">
                                            <flux:heading>Archiver cette correspondance ?</flux:heading>
                                            <flux:text>Elle ne sera plus disponible pour les nouvelles requêtes. Les liens et les anciens envois sont conservés.</flux:text>
                                            <flux:button type="submit" variant="danger">Confirmer l’archivage</flux:button>
                                        </form>
                                    </flux:modal>
                                @endcan
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-3">Aucune correspondance trouvée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $mappings->links() }}
    </section>
</x-layouts::app>
