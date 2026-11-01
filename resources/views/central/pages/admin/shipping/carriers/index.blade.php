<x-layouts::app title="Transporteurs">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Transporteurs communs</flux:heading>
        <flux:text>Catalogue partagé par les boutiques, sans leurs identifiants privés. Yalidine est fourni par défaut ; vous pouvez ajouter d’autres transporteurs.</flux:text>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @can('create', \App\Models\Central\Shipping\ShippingCarrier::class)
            <div><flux:button :href="route('central.carriers.create')" variant="primary">Ajouter un transporteur</flux:button></div>
        @endcan
        <form method="GET" action="{{ route('central.carriers.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" label="Rechercher" :value="request('search')" placeholder="Nom ou code" />
            <flux:select name="is_active" label="Disponibilité">
                <option value="">Toutes</option>
                <option value="1" @selected(request('is_active') === '1')>Actifs</option>
                <option value="0" @selected(request('is_active') === '0')>Inactifs</option>
            </flux:select>
            <flux:select name="archived" label="Catalogue">
                <option value="0" @selected(! request()->boolean('archived'))>Actuel</option>
                <option value="1" @selected(request()->boolean('archived'))>Archivé</option>
            </flux:select>
            <flux:button type="submit">Filtrer</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-4">Transporteur</th><th class="p-4">Code</th><th class="p-4">État</th><th class="p-4">Actions</th></tr></thead>
                <tbody>
                    @forelse ($carriers as $carrier)
                        <tr>
                            <td class="p-4">{{ $carrier->name }}</td>
                            <td class="p-4">{{ $carrier->code }}</td>
                            <td class="p-4">{{ $carrier->trashed() ? 'Archivé' : ($carrier->is_active ? 'Actif' : 'Inactif') }}</td>
                            <td class="p-4"><div class="flex flex-wrap gap-2">
                                <flux:button :href="route('central.carriers.show', $carrier)">Informations</flux:button>
                                @can('update', $carrier)<flux:button :href="route('central.carriers.edit', $carrier)">Modifier</flux:button>@endcan
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4">Aucun transporteur trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $carriers->links() }}
    </section>
</x-layouts::app>
