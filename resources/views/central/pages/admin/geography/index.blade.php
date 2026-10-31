<x-layouts::app :title="__('Wilayas et communes')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Wilayas et communes</flux:heading>
        <flux:text>Référentiel géographique central : identités stables, rattachements et provenance des zones.</flux:text>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <div class="flex flex-wrap gap-3">
            @can('create', \App\Models\Central\Geography\GeographicArea::class)
                <flux:button :href="route('central.geography.create')" variant="primary">Ajouter une zone</flux:button>
            @endcan
        </div>
        <form method="GET" action="{{ route('central.geography.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" label="Rechercher" :value="request('search')" placeholder="Nom français, arabe ou code" />
            <flux:select name="country_uuid" label="Pays">
                <option value="">Tous les pays</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->uuid }}" @selected(request('country_uuid') === $country->uuid)>{{ $country->name_fr }}</option>
                @endforeach
            </flux:select>
            <flux:select name="type" label="Type">
                <option value="">Tous les types</option>
                @foreach (\App\Enums\Central\Geography\GeoZoneTypeEnum::cases() as $type)
                    <option value="{{ $type->value }}" @selected((string) request('type') === (string) $type->value)>{{ $type->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select name="parent_uuid" label="Wilaya parente">
                <option value="">Toutes les wilayas</option>
                @foreach ($provinces as $province)
                    <option value="{{ $province->uuid }}" @selected(request('parent_uuid') === $province->uuid)>{{ $province->code }} — {{ $province->name_fr }}</option>
                @endforeach
            </flux:select>
            <flux:select name="state" label="État">
                <option value="all" @selected(request('state', 'all') === 'all')>Tous les états</option>
                <option value="active" @selected(request('state') === 'active')>Actives</option>
                <option value="inactive" @selected(request('state') === 'inactive')>Inactives</option>
                <option value="archived" @selected(request('state') === 'archived')>Archivées</option>
            </flux:select>
            <flux:button type="submit">Filtrer</flux:button>
            <flux:button :href="route('central.geography.index')">Réinitialiser</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Zones géographiques correspondant aux filtres</caption>
                <thead><tr>
                    @foreach (['Zone', 'Type', 'Pays', 'Wilaya parente', 'État', 'Source et date d’effet', 'Actions'] as $heading)<th scope="col" class="p-4">{{ $heading }}</th>@endforeach
                </tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($areas as $area)
                        <tr>
                            <th scope="row" class="p-4 font-normal">{{ $area->name_fr }}<div class="text-xs">{{ $area->code }}</div>@if ($area->name_ar)<div dir="rtl" lang="ar">{{ $area->name_ar }}</div>@endif</th>
                            <td class="p-4">{{ $area->type->label() }}</td>
                            <td class="p-4">{{ $area->country->name_fr }}</td>
                            <td class="p-4">{{ $area->parent?->name_fr ?? 'Aucune' }}</td>
                            <td class="p-4">{{ $area->trashed() ? 'Archivée' : ($area->is_active ? 'Active' : 'Inactive') }}</td>
                            <td class="p-4">{{ $area->reference_source }}<div>{{ $area->reference_version }}</div><div>{{ $area->effective_at?->format('d/m/Y') ?? 'Non renseignée' }}</div></td>
                            <td class="p-4"><div class="flex flex-wrap gap-2">
                                <flux:button :href="route('central.geography.show', $area->uuid)">Informations</flux:button>
                                @can('update', $area)<flux:button :href="route('central.geography.edit', $area->uuid)">Modifier</flux:button>@endcan
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4">Aucune zone trouvée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $areas->links() }}
    </section>
</x-layouts::app>
