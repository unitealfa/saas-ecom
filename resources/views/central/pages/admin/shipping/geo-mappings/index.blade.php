<x-layouts::app title="Correspondances géographiques">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Correspondances géographiques</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Associez une wilaya ou une commune d’Aydra aux codes réellement fournis par le transporteur. Aucun code postal ou identifiant interne n’est converti automatiquement en code transporteur.</p></div>
            @can('create', \App\Models\Central\Shipping\CarrierGeoMapping::class)
                <flux:button :href="route('central.carrier-mappings.create', ['carrier_uuid' => request('carrier_uuid')])" variant="primary" icon="plus">Créer une correspondance</flux:button>
            @endcan
        </header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Rechercher une correspondance</h2><p class="aydra-muted mt-1 text-sm">Les suggestions proviennent des résultats affichés sur cette page.</p><form method="GET" action="{{ route('central.carrier-mappings.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-56" x-data="{ value: '', label: '' }" data-value="{{ request('carrier_uuid', '') }}" data-label="{{ $carriers->firstWhere('uuid', request('carrier_uuid'))?->name ?? 'Tous' }}" x-init="value = $el.dataset.value; label = $el.dataset.label"><label id="mapping-carrier-label" class="mb-2 block text-sm font-medium">Transporteur</label><input type="hidden" name="carrier_uuid" :value="value" value="{{ request('carrier_uuid', '') }}"><flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" aria-labelledby="mapping-carrier-label mapping-carrier-value"><span id="mapping-carrier-value" x-text="label">Tous</span></flux:button><flux:menu class="max-h-80 overflow-y-auto"><flux:menu.item type="button" data-value="" @click="value = $el.dataset.value; label = $el.textContent.trim()">Tous</flux:menu.item>@foreach ($carriers as $carrier)<flux:menu.item type="button" data-value="{{ $carrier->uuid }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $carrier->name }}</flux:menu.item>@endforeach</flux:menu></flux:dropdown></div>
            <flux:input name="search" label="Zone, code ou nom externe" :value="request('search')" type="search" icon="magnifying-glass" list="mapping-suggestions" placeholder="Zone, code ou nom externe" class="min-w-0 flex-1 sm:min-w-72" />
            <datalist id="mapping-suggestions">@foreach ($mappings as $mapping)<option value="{{ $mapping->area->name_fr }}"></option><option value="{{ $mapping->external_code }}"></option>@if($mapping->external_name)<option value="{{ $mapping->external_name }}"></option>@endif @endforeach</datalist>
            <div class="w-full sm:w-48" x-data="{ value: '', label: '' }" data-value="{{ request('is_active', '') }}" data-label="{{ match ((string) request('is_active', '')) { '1' => 'Actives', '0' => 'Inactives / archivées', default => 'Toutes' } }}" x-init="value = $el.dataset.value; label = $el.dataset.label"><label id="mapping-active-label" class="mb-2 block text-sm font-medium">Disponibilité</label><input type="hidden" name="is_active" :value="value" value="{{ request('is_active', '') }}"><flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" aria-labelledby="mapping-active-label mapping-active-value"><span id="mapping-active-value" x-text="label">Toutes</span></flux:button><flux:menu>@foreach (['' => 'Toutes', '1' => 'Actives', '0' => 'Inactives / archivées'] as $value => $label)<flux:menu.item type="button" data-value="{{ $value }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $label }}</flux:menu.item>@endforeach</flux:menu></flux:dropdown></div>
            @if(request()->filled('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">@endif
            <flux:button type="submit" variant="primary">Rechercher</flux:button><flux:button :href="route('central.carrier-mappings.index')">Effacer</flux:button>
        </form></section>
        @php($sortBy = request('sort')) @php($sortDirection = request('direction', 'asc'))
        <section class="aydra-users-panel"><div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Correspondances</h2><p class="aydra-muted mt-1 text-sm">{{ $mappings->total() }} {{ $mappings->total() === 1 ? 'correspondance trouvée' : 'correspondances trouvées' }}</p></div></div><div class="aydra-users-table"><flux:table class="whitespace-normal"><flux:table.columns>@foreach (['carrier' => 'Transporteur', 'area' => 'Zone Aydra', 'external_code' => 'Code externe', 'external_name' => 'Nom externe', 'external_province_code' => 'Code wilaya externe', 'mapping_version' => 'Source / version', 'is_active' => 'État', 'actions' => 'Actions'] as $column => $heading) @if(in_array($column, ['external_code', 'external_name', 'external_province_code', 'mapping_version', 'is_active'], true))<flux:table.column sortable :sorted="$sortBy === $column" :direction="$sortDirection" :aria-sort="$sortBy === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" :data-sort-url="route('central.carrier-mappings.index', [...request()->only(['search', 'carrier_uuid', 'is_active']), 'sort' => $column, 'direction' => $sortBy === $column && $sortDirection === 'asc' ? 'desc' : 'asc'])" onclick="window.location.assign(this.dataset.sortUrl)">{{ $heading }}</flux:table.column>@else<flux:table.column>{{ $heading }}</flux:table.column>@endif @endforeach</flux:table.columns><flux:table.rows>
                    @forelse ($mappings as $mapping)
                        <flux:table.row :key="$mapping->uuid">
                            <flux:table.cell variant="strong">{{ $mapping->carrier->name }}</flux:table.cell><flux:table.cell>{{ $mapping->area->name_fr }}<div class="aydra-muted mt-1 text-xs">{{ $mapping->zone_type->label() }} — {{ $mapping->area->code }}</div></flux:table.cell><flux:table.cell>{{ $mapping->external_code }}</flux:table.cell><flux:table.cell>{{ $mapping->external_name }}</flux:table.cell><flux:table.cell>{{ $mapping->external_province_code }}</flux:table.cell><flux:table.cell>{{ $mapping->verification_source }}<div class="aydra-muted mt-1 text-xs">Version {{ $mapping->mapping_version }} — {{ $mapping->verified_at?->format('d/m/Y H:i') ?? 'Non vérifiée' }}</div></flux:table.cell><flux:table.cell><flux:badge size="sm" :color="$mapping->is_active ? 'green' : 'zinc'">{{ $mapping->is_active ? 'Active' : 'Inactive / archivée' }}</flux:badge></flux:table.cell><flux:table.cell><div class="flex flex-wrap gap-2">
                                @can('update', $mapping)
                                    <flux:button :href="route('central.carrier-mappings.edit', $mapping)">Modifier une correspondance</flux:button>
                                @endcan
                                @can('archive', $mapping)
                                    <flux:modal.trigger :name="'archive-mapping-'.$mapping->uuid"><flux:button variant="danger">Archiver une correspondance</flux:button></flux:modal.trigger>
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
                                @if (! $mapping->is_active)
                                    @can('saas.carrier-mappings.update')
                                        <flux:button disabled>Modifier une correspondance</flux:button>
                                    @endcan
                                    @can('saas.carrier-mappings.archive')
                                        <flux:button disabled>Archiver une correspondance</flux:button>
                                    @endcan
                                @endif
                            </div></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="8"><div class="aydra-users-empty">Aucune correspondance trouvée.</div></flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows></flux:table>@if ($mappings->hasPages())<nav aria-label="Pagination des correspondances" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $mappings->currentPage() }} sur {{ $mappings->lastPage() }}</span><div class="flex gap-2">@if ($mappings->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$mappings->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($mappings->hasMorePages())<flux:button :href="$mappings->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif</div></section>
    </section>
</x-layouts::app>
