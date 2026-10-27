<x-layouts::app :title="__('Fonctionnalités commerciales')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Fonctionnalités commerciales du SaaS</flux:heading>
        <flux:text>Ce catalogue décrit les possibilités des offres, comme le nombre de boutiques. Les permissions du personnel SaaS sont gérées séparément.</flux:text>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @can('create', \App\Models\Central\Features\Feature::class)
            <div><flux:button :href="route('central.features.create')" variant="primary">Créer une fonctionnalité</flux:button></div>
        @endcan
        <form method="GET" action="{{ route('central.features.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" label="Rechercher" :value="request('search')" placeholder="Nom ou code" />
            <flux:select name="is_active" label="Disponibilité">
                <option value="">Toutes</option>
                <option value="1" @selected(request('is_active') === '1')>Actives</option>
                <option value="0" @selected(request('is_active') === '0')>Inactives</option>
            </flux:select>
            <flux:select name="archived" label="Catalogue">
                <option value="0" @selected(! request()->boolean('archived'))>Actuel</option>
                <option value="1" @selected(request()->boolean('archived'))>Archivé</option>
            </flux:select>
            <flux:button type="submit">Filtrer</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr>
                    @foreach (['Fonctionnalité', 'Type', 'Portée', 'Période', 'État', 'Offres liées', 'Actions'] as $heading)<th class="p-4">{{ $heading }}</th>@endforeach
                </tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($features as $feature)
                        <tr>
                            <td class="p-4">{{ $feature->name }}<div class="text-xs">{{ $feature->code }}</div>
                                @if ($feature->isSystem())<flux:badge>Système</flux:badge>@endif
                            </td>
                            <td class="p-4">{{ $feature->value_type->label() }}</td>
                            <td class="p-4">{{ $feature->quota_scope->label() }}</td>
                            <td class="p-4">{{ $feature->period->label() }}</td>
                            <td class="p-4">{{ $feature->trashed() ? 'Archivée' : ($feature->is_active ? 'Active' : 'Inactive') }}</td>
                            <td class="p-4">{{ $feature->plan_features_count }}</td>
                            <td class="p-4"><div class="flex flex-wrap gap-2">
                                <flux:button :href="route('central.features.show', $feature)">Informations</flux:button>
                                @can('update', $feature)<flux:button :href="route('central.features.edit', $feature)">Modifier</flux:button>@endcan
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4">Aucune fonctionnalité trouvée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $features->links() }}
    </section>
</x-layouts::app>
