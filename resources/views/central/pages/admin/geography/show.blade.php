<x-layouts::app :title="$area->name_fr">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $area->name_fr }}</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt>Code permanent</dt><dd>{{ $area->code }}</dd></div>
            <div><dt>Type</dt><dd>{{ $area->type->label() }}</dd></div>
            <div><dt>Pays</dt><dd>{{ $area->country->name_fr }}</dd></div>
            <div><dt>Wilaya parente</dt><dd>@if ($area->parent)<a class="underline" href="{{ route('central.geography.show', $area->parent->uuid) }}">{{ $area->parent->name_fr }}</a>@else Aucune @endif</dd></div>
            <div><dt>Nom arabe</dt><dd dir="rtl" lang="ar">{{ $area->name_ar ?? '—' }}</dd></div>
            <div><dt>État</dt><dd>{{ $area->trashed() ? 'Archivée' : ($area->is_active ? 'Active' : 'Inactive') }}</dd></div>
            <div><dt>Source</dt><dd>{{ $area->reference_source }}</dd></div>
            <div><dt>Version</dt><dd>{{ $area->reference_version }}</dd></div>
            <div><dt>Date de prise d’effet</dt><dd>{{ $area->effective_at?->format('d/m/Y') ?? 'Non renseignée' }}</dd></div>
        </dl>
        <flux:text>Les codes « dz-{post_code} » reprennent le code postal du fichier source ; ils restent distincts des identifiants internes et des codes propres aux transporteurs.</flux:text>
        <div class="flex flex-wrap gap-3">
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
        </div>
        @if ($area->type === \App\Enums\Central\Geography\GeoZoneTypeEnum::PROVINCE)
            <flux:heading size="lg" level="2">Communes de cette wilaya</flux:heading>
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Communes rattachées à {{ $area->name_fr }}</caption>
                    <thead><tr><th scope="col" class="p-4">Code</th><th scope="col" class="p-4">Commune</th><th scope="col" class="p-4">État</th><th scope="col" class="p-4">Actions</th></tr></thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($communes as $commune)
                            <tr>
                                <td class="p-4">{{ $commune->code }}</td>
                                <th scope="row" class="p-4 font-normal">{{ $commune->name_fr }}@if ($commune->name_ar)<div dir="rtl" lang="ar">{{ $commune->name_ar }}</div>@endif</th>
                                <td class="p-4">{{ $commune->trashed() ? 'Archivée' : ($commune->is_active ? 'Active' : 'Inactive') }}</td>
                                <td class="p-4"><flux:button :href="route('central.geography.show', $commune->uuid)">Informations</flux:button></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4">Aucune commune rattachée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $communes->links() }}
        @endif
    </section>
</x-layouts::app>
