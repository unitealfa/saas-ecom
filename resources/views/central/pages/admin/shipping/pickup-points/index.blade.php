<x-layouts::app title="Bureaux de retrait">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Bureaux de retrait</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <div class="flex gap-3">
            @can('create', \App\Models\Central\Shipping\PickupPoint::class)<flux:button :href="route('central.pickup-points.create')" variant="primary">Ajouter un bureau</flux:button>@endcan
            @can('import', \App\Models\Central\Shipping\PickupPoint::class)<flux:button :href="route('central.pickup-points.import')">Importer les bureaux officiels</flux:button>@endcan
        </div>
        <form method="GET" action="{{ route('central.pickup-points.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" label="Nom ou code" :value="request('search')" />
            <flux:select name="carrier_uuid" label="Transporteur">
                <option value="">Tous</option>
                @foreach ($carriers as $carrier)<option value="{{ $carrier->uuid }}" @selected(request('carrier_uuid') === $carrier->uuid)>{{ $carrier->name }}</option>@endforeach
            </flux:select>
            <flux:select name="is_carrier_active" label="Disponibilité">
                <option value="">Toutes</option>
                <option value="1" @selected(request('is_carrier_active') === '1')>Actif</option>
                <option value="0" @selected(request('is_carrier_active') === '0')>Inactif</option>
            </flux:select>
            <flux:checkbox name="archived" value="1" label="Archives uniquement" :checked="request()->boolean('archived')" />
            <flux:button type="submit">Filtrer</flux:button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead><tr><th class="p-2">Transporteur</th><th class="p-2">Code</th><th class="p-2">Bureau</th><th class="p-2">Wilaya / commune</th><th class="p-2">État</th><th class="p-2">Actions</th></tr></thead>
                <tbody>
                    @forelse ($points as $point)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-2">{{ $point->carrier->name }}</td>
                            <td class="p-2">{{ $point->external_code }}</td>
                            <td class="p-2">{{ $point->name }}</td>
                            <td class="p-2">{{ $point->province->name_fr }} / {{ $point->municipality?->name_fr ?? 'Non précisée' }}</td>
                            <td class="p-2">{{ $point->trashed() ? 'Archivé' : ($point->is_carrier_active ? 'Actif' : 'Inactif') }}</td>
                            <td class="p-2">@include('central.pages.admin.shipping.pickup-points.actions')</td>
                        </tr>
                    @empty<tr><td colspan="6" class="p-2">Aucun bureau de retrait.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $points->links() }}
    </section>
</x-layouts::app>
