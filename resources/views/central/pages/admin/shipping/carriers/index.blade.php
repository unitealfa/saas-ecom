<x-layouts::app title="Transporteurs">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Transporteurs communs</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Catalogue partagé par les boutiques, sans leurs identifiants privés. Yalidine est fourni par défaut ; vous pouvez ajouter d’autres transporteurs.</p></div>
        @can('create', \App\Models\Central\Shipping\ShippingCarrier::class)
            <flux:button :href="route('central.carriers.create')" variant="primary" icon="plus">Ajouter un transporteur</flux:button>
        @endcan
        </header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Rechercher un transporteur</h2><p class="aydra-muted mt-1 text-sm">Les suggestions proviennent des résultats affichés sur cette page.</p>
        <form method="GET" action="{{ route('central.carriers.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
            <flux:input name="search" label="Nom ou code" :value="request('search')" type="search" icon="magnifying-glass" list="carrier-suggestions" placeholder="Nom ou code" class="min-w-0 flex-1 sm:min-w-72" />
            <datalist id="carrier-suggestions">@foreach ($carriers as $carrier)<option value="{{ $carrier->name }}"></option><option value="{{ $carrier->code }}"></option>@endforeach</datalist>
            @foreach (['is_active' => ['Disponibilité', ['' => 'Toutes', '1' => 'Actifs', '0' => 'Inactifs']], 'archived' => ['Catalogue', ['0' => 'Actuel', '1' => 'Archivé']]] as $field => [$label, $options])
                @php($value = (string) request($field, $field === 'archived' ? '0' : ''))
                <div class="w-full sm:w-44" x-data="{ value: '', label: '' }" data-value="{{ $value }}" data-label="{{ $options[$value] ?? reset($options) }}" x-init="value = $el.dataset.value; label = $el.dataset.label"><label id="carrier-{{ $field }}-label" class="mb-2 block text-sm font-medium">{{ $label }}</label><input type="hidden" name="{{ $field }}" :value="value" value="{{ $value }}"><flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" :aria-labelledby="'carrier-'.$field.'-label carrier-'.$field.'-value'"><span id="carrier-{{ $field }}-value" x-text="label">{{ $options[$value] ?? reset($options) }}</span></flux:button><flux:menu>@foreach ($options as $optionValue => $optionLabel)<flux:menu.item type="button" data-value="{{ $optionValue }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $optionLabel }}</flux:menu.item>@endforeach</flux:menu></flux:dropdown></div>
            @endforeach
            @if(request()->filled('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">@endif
            <flux:button type="submit" variant="primary">Rechercher</flux:button><flux:button :href="route('central.carriers.index')">Effacer</flux:button>
        </form></section>
        <section class="aydra-users-panel"><div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Transporteurs</h2><p class="aydra-muted mt-1 text-sm">{{ $carriers->total() }} {{ $carriers->total() === 1 ? 'transporteur trouvé' : 'transporteurs trouvés' }}</p></div></div>
        @php($sortBy = request('sort', 'name')) @php($sortDirection = request('direction', 'asc'))
        <div class="aydra-users-table"><flux:table class="whitespace-normal"><flux:table.columns>@foreach (['name' => 'Transporteur', 'code' => 'Code', 'is_active' => 'État', 'actions' => 'Actions'] as $column => $heading) @if ($column !== 'actions')<flux:table.column sortable :sorted="$sortBy === $column" :direction="$sortDirection" :aria-sort="$sortBy === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" :data-sort-url="route('central.carriers.index', [...request()->only(['search', 'is_active', 'archived']), 'sort' => $column, 'direction' => $sortBy === $column && $sortDirection === 'asc' ? 'desc' : 'asc'])" onclick="window.location.assign(this.dataset.sortUrl)">{{ $heading }}</flux:table.column>@else<flux:table.column>{{ $heading }}</flux:table.column>@endif @endforeach</flux:table.columns><flux:table.rows>
                    @forelse ($carriers as $carrier)
                        <flux:table.row :key="$carrier->uuid">
                            <flux:table.cell variant="strong">{{ $carrier->name }}</flux:table.cell><flux:table.cell>{{ $carrier->code }}</flux:table.cell><flux:table.cell><flux:badge size="sm" :color="$carrier->trashed() ? 'zinc' : ($carrier->is_active ? 'green' : 'amber')">{{ $carrier->trashed() ? 'Archivé' : ($carrier->is_active ? 'Actif' : 'Inactif') }}</flux:badge></flux:table.cell>
                            <flux:table.cell><div class="flex flex-wrap gap-2">
                                <flux:button :href="route('central.carriers.show', $carrier)">Informations</flux:button>
                                @can('update', $carrier)<flux:button :href="route('central.carriers.edit', $carrier)">Modifier</flux:button>@endcan
                            </div></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="4"><div class="aydra-users-empty">Aucun transporteur trouvé.</div></flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows></flux:table>
            @if ($carriers->hasPages())<nav aria-label="Pagination des transporteurs" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $carriers->currentPage() }} sur {{ $carriers->lastPage() }}</span><div class="flex gap-2">@if ($carriers->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$carriers->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($carriers->hasMorePages())<flux:button :href="$carriers->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif
        </div></section>
    </section>
</x-layouts::app>
