<x-layouts::app :title="__('Wilayas et communes')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Wilayas et communes</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Référentiel géographique central : identités stables, rattachements et provenance des zones.</p></div>@can('create', \App\Models\Central\Geography\GeographicArea::class)<flux:button :href="route('central.geography.create')" variant="primary" icon="plus">Ajouter une zone</flux:button>@endcan</header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        @php
            $filters = [
                'country_uuid' => ['Pays', 'Tous les pays', $countries->map(fn ($country) => ['value' => $country->uuid, 'label' => $country->name_fr])],
                'type' => ['Type', 'Tous les types', collect(\App\Enums\Central\Geography\GeoZoneTypeEnum::cases())->map(fn ($type) => ['value' => (string) $type->value, 'label' => $type->label()])],
                'parent_uuid' => ['Wilaya parente', 'Toutes les wilayas', $provinces->map(fn ($province) => ['value' => $province->uuid, 'label' => $province->code.' — '.$province->name_fr])],
                'state' => ['État', 'Tous les états', collect([['value' => 'active', 'label' => 'Actives'], ['value' => 'inactive', 'label' => 'Inactives'], ['value' => 'archived', 'label' => 'Archivées']])],
            ];
        @endphp
        <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="geography-search"><h2 id="geography-search" class="aydra-heading text-lg font-semibold">Rechercher une zone</h2><p class="aydra-muted mt-1 text-sm">Les suggestions proviennent des zones affichées sur cette page.</p>
            <form method="GET" action="{{ route('central.geography.index') }}" class="mt-5 space-y-4">
                <div class="flex flex-wrap items-end gap-3"><flux:input name="search" label="Nom ou code" :value="request('search')" type="search" icon="magnifying-glass" placeholder="Nom français, arabe ou code" list="geography-suggestions" class="min-w-0 flex-1 sm:min-w-72" /><datalist id="geography-suggestions">@foreach ($areas as $area)<option value="{{ $area->name_fr }}"></option><option value="{{ $area->code }}"></option>@if ($area->name_ar)<option value="{{ $area->name_ar }}"></option>@endif @endforeach</datalist>
                    @if (request()->filled('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">@endif
                    <flux:button type="submit" variant="primary">Rechercher</flux:button><flux:button :href="route('central.geography.index')">Effacer</flux:button>
                </div>
                <details class="aydra-animated-details rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700" @if(request()->filled('country_uuid') || request()->filled('parent_uuid') || request()->filled('type') || (request()->filled('state') && request('state') !== 'all')) open @endif>
                    <summary class="cursor-pointer text-sm font-medium">Filtres avancés</summary><div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($filters as $field => [$label, $placeholder, $options])
                            @php($selectedValue = (string) request($field, $field === 'state' ? 'all' : ''))
                            @php($selectedLabel = $options->firstWhere('value', $selectedValue)['label'] ?? $placeholder)
                            <div x-data="{ value: '', label: '' }" data-value="{{ $selectedValue }}" data-label="{{ $selectedLabel }}" x-init="value = $el.dataset.value; label = $el.dataset.label">
                                <label id="geography-{{ $field }}-label" class="mb-2 block text-sm font-medium">{{ $label }}</label><input type="hidden" name="{{ $field }}" :value="value" value="{{ $selectedValue }}">
                                <flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" :aria-labelledby="'geography-'.$field.'-label geography-'.$field.'-value'"><span id="geography-{{ $field }}-value" x-text="label">{{ $selectedLabel }}</span></flux:button><flux:menu class="max-h-80 overflow-y-auto"><flux:menu.item type="button" data-value="{{ $field === 'state' ? 'all' : '' }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $placeholder }}</flux:menu.item>@foreach ($options as $option)<flux:menu.item type="button" data-value="{{ $option['value'] }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $option['label'] }}</flux:menu.item>@endforeach</flux:menu></flux:dropdown>
                            </div>
                        @endforeach
                    </div>
                </details>
            </form>
        </section>
        @php($sortBy = request('sort', 'code')) @php($sortDirection = request('direction', 'asc'))
        <section class="aydra-users-panel"><div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Zones géographiques</h2><p class="aydra-muted mt-1 text-sm">{{ $areas->total() }} {{ $areas->total() === 1 ? 'zone trouvée' : 'zones trouvées' }}</p></div></div>
            <div class="aydra-users-table"><flux:table class="whitespace-normal"><flux:table.columns>
                @foreach (['name_fr' => 'Zone', 'code' => 'Code', 'type' => 'Type', 'country' => 'Pays', 'parent' => 'Wilaya parente', 'is_active' => 'État', 'effective_at' => 'Source et date d’effet', 'actions' => 'Actions'] as $column => $heading)
                    @if (in_array($column, ['code', 'name_fr', 'type', 'is_active', 'effective_at'], true))<flux:table.column sortable :sorted="$sortBy === $column" :direction="$sortDirection" :aria-sort="$sortBy === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" :data-sort-url="route('central.geography.index', [...request()->only(['search', 'country_uuid', 'type', 'parent_uuid', 'state']), 'sort' => $column, 'direction' => $sortBy === $column && $sortDirection === 'asc' ? 'desc' : 'asc'])" onclick="window.location.assign(this.dataset.sortUrl)">{{ $heading }}</flux:table.column>@else <flux:table.column>{{ $heading }}</flux:table.column> @endif
                @endforeach
            </flux:table.columns><flux:table.rows>
                @forelse ($areas as $area)
                    <flux:table.row :key="$area->uuid"><flux:table.cell variant="strong">{{ $area->name_fr }}@if ($area->name_ar)<div class="aydra-muted mt-1 text-xs" dir="rtl" lang="ar">{{ $area->name_ar }}</div>@endif</flux:table.cell><flux:table.cell>{{ $area->code }}</flux:table.cell><flux:table.cell>{{ $area->type->label() }}</flux:table.cell><flux:table.cell>{{ $area->country->name_fr }}</flux:table.cell><flux:table.cell>{{ $area->parent?->name_fr ?? 'Aucune' }}</flux:table.cell><flux:table.cell><flux:badge size="sm" :color="$area->trashed() ? 'zinc' : ($area->is_active ? 'green' : 'amber')">{{ $area->trashed() ? 'Archivée' : ($area->is_active ? 'Active' : 'Inactive') }}</flux:badge></flux:table.cell><flux:table.cell>{{ $area->reference_source }}<div class="aydra-muted mt-1 text-xs">{{ $area->reference_version }} · {{ $area->effective_at?->format('d/m/Y') ?? 'Non renseignée' }}</div></flux:table.cell><flux:table.cell><flux:dropdown position="bottom" align="end"><flux:button type="button" variant="ghost" size="sm" icon="bars-3" aria-label="Actions" title="Actions" /><flux:menu><flux:menu.item :href="route('central.geography.show', $area->uuid)">Informations</flux:menu.item>@can('update', $area)<flux:menu.item :href="route('central.geography.edit', $area->uuid)">Modifier</flux:menu.item>@endcan</flux:menu></flux:dropdown></flux:table.cell></flux:table.row>
                @empty <flux:table.row><flux:table.cell colspan="8"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucune zone trouvée.</p><p class="aydra-muted mt-1 text-sm">Essayez d’autres critères de recherche.</p></div></flux:table.cell></flux:table.row> @endforelse
            </flux:table.rows></flux:table>
            @if ($areas->hasPages())<nav aria-label="Pagination des zones géographiques" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $areas->currentPage() }} sur {{ $areas->lastPage() }}</span><div class="flex gap-2">@if ($areas->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$areas->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($areas->hasMorePages())<flux:button :href="$areas->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif
            </div>
        </section>
    </section>
</x-layouts::app>
