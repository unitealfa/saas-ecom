<x-layouts::app :title="$area->name_fr">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="min-w-0 space-y-2"><h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">{{ $area->name_fr }}</h1><div class="flex flex-wrap items-center gap-2"><span class="aydra-muted text-sm">{{ $area->code }} · {{ $area->type->label() }}</span><flux:badge size="sm" :color="$area->trashed() ? 'zinc' : ($area->is_active ? 'green' : 'amber')">{{ $area->trashed() ? 'Archivée' : ($area->is_active ? 'Active' : 'Inactive') }}</flux:badge></div></div><flux:button :href="route('central.geography.index')" icon="arrow-left">Retour au référentiel</flux:button></header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Informations de la zone</h2><p class="aydra-muted mt-1 text-sm">Identité, rattachement et provenance.</p><dl class="aydra-detail-grid mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt>Code permanent</dt><dd>{{ $area->code }}</dd></div>
            <div><dt>Type</dt><dd>{{ $area->type->label() }}</dd></div>
            <div><dt>Pays</dt><dd>{{ $area->country->name_fr }}</dd></div>
            <div><dt>Wilaya parente</dt><dd>@if ($area->parent)<a class="underline" href="{{ route('central.geography.show', $area->parent->uuid) }}">{{ $area->parent->name_fr }}</a>@else Aucune @endif</dd></div>
            <div><dt>Nom arabe</dt><dd dir="rtl" lang="ar">{{ $area->name_ar ?? '—' }}</dd></div>
            <div><dt>État</dt><dd>{{ $area->trashed() ? 'Archivée' : ($area->is_active ? 'Active' : 'Inactive') }}</dd></div>
            <div><dt>Source</dt><dd>{{ $area->reference_source }}</dd></div>
            <div><dt>Version</dt><dd>{{ $area->reference_version }}</dd></div>
            <div><dt>Date de prise d’effet</dt><dd>{{ $area->effective_at?->format('d/m/Y') ?? 'Non renseignée' }}</dd></div>
        </dl></section>
        <flux:text>Les codes « dz-{post_code} » reprennent le code postal du fichier source ; ils restent distincts des identifiants internes et des codes propres aux transporteurs.</flux:text>
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-4 text-lg font-semibold">Actions</h2><div class="flex flex-wrap gap-3">
            @can('update', $area)<flux:button :href="route('central.geography.edit', $area->uuid)">Modifier</flux:button>@endcan
            @can('activate', $area)
                <form method="POST" action="{{ route('central.geography.activate', $area->uuid) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_state" value="{{ $area->stateToken() }}">
                    <input type="hidden" name="is_active" value="{{ $area->is_active ? '0' : '1' }}">
                    <flux:button type="submit">{{ $area->is_active ? 'Désactiver' : 'Activer' }}</flux:button>
                </form>
            @endcan
            @can('archive', $area)
                <form method="POST" action="{{ route('central.geography.archive', $area->uuid) }}" x-data x-on:submit="if (! window.confirm('Archiver cette zone géographique ? Ses références historiques seront conservées.')) $event.preventDefault()">
                    @csrf @method('DELETE')
                    <input type="hidden" name="expected_state" value="{{ $area->stateToken() }}">
                    <flux:button type="submit" variant="danger">Archiver</flux:button>
                </form>
            @endcan
            <flux:button :href="route('central.geography.index')">Retour au référentiel</flux:button>
        </div></section>
        @if ($area->type === \App\Enums\Central\Geography\GeoZoneTypeEnum::PROVINCE)
            <section class="aydra-users-panel"><div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Communes de cette wilaya</h2><p class="aydra-muted mt-1 text-sm">{{ $communes->total() }} {{ $communes->total() === 1 ? 'commune rattachée' : 'communes rattachées' }}</p></div></div>
            <div class="aydra-users-table"><flux:table class="whitespace-normal">
                    <flux:table.columns><flux:table.column>Code</flux:table.column><flux:table.column>Commune</flux:table.column><flux:table.column>État</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($communes as $commune)
                            <flux:table.row :key="$commune->uuid"><flux:table.cell>{{ $commune->code }}</flux:table.cell><flux:table.cell variant="strong">{{ $commune->name_fr }}@if ($commune->name_ar)<div class="aydra-muted mt-1 text-xs" dir="rtl" lang="ar">{{ $commune->name_ar }}</div>@endif</flux:table.cell><flux:table.cell><flux:badge size="sm" :color="$commune->trashed() ? 'zinc' : ($commune->is_active ? 'green' : 'amber')">{{ $commune->trashed() ? 'Archivée' : ($commune->is_active ? 'Active' : 'Inactive') }}</flux:badge></flux:table.cell><flux:table.cell><flux:button :href="route('central.geography.show', $commune->uuid)">Informations</flux:button></flux:table.cell></flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4"><div class="aydra-users-empty">Aucune commune rattachée.</div></flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                @if ($communes->hasPages())<nav aria-label="Pagination des communes" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $communes->currentPage() }} sur {{ $communes->lastPage() }}</span><div class="flex gap-2">@if ($communes->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$communes->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($communes->hasMorePages())<flux:button :href="$communes->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif
            </div></section>
        @endif
    </section>
</x-layouts::app>
