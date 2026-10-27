<x-layouts::app :title="__('Pays')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Pays disponibles</flux:heading>
        <flux:text>Les pays actifs peuvent être choisis lors de l’inscription. Désactiver un pays conserve les comptes et les zones existants.</flux:text>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        @can('create', \App\Models\Central\Countries\Country::class)
            <flux:button :href="route('central.countries.create')" variant="primary">Créer un pays</flux:button>
        @endcan
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr>
                    @foreach (['Code', 'Français', 'Anglais', 'Arabe', 'Disponibilité', 'Actions'] as $heading)
                        <th class="p-4">{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($countries as $country)
                        <tr>
                            <td class="p-4">{{ $country->code }}</td>
                            <td class="p-4">{{ $country->name_fr }}</td>
                            <td class="p-4">{{ $country->name_en }}</td>
                            <td class="p-4" dir="rtl">{{ $country->name_ar ?? '—' }}</td>
                            <td class="p-4">{{ $country->is_active ? 'Actif' : 'Désactivé' }}</td>
                            <td class="p-4">
                                <div class="flex flex-wrap gap-2">
                                    @can('update', $country)
                                        <flux:button :href="route('central.countries.edit', $country)">Modifier</flux:button>
                                    @endcan
                                    @can('activate', $country)
                                        <form method="POST" action="{{ route('central.countries.activate', $country) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $country->is_active ? '0' : '1' }}">
                                            <flux:button type="submit">{{ $country->is_active ? 'Désactiver' : 'Activer' }}</flux:button>
                                        </form>
                                    @endcan
                                    @can('delete', $country)
                                        <form method="POST" action="{{ route('central.countries.destroy', $country) }}" x-data x-on:submit="if (! window.confirm('Retirer ce pays du catalogue ?')) $event.preventDefault()">
                                            @csrf @method('DELETE')
                                            <flux:button type="submit" variant="danger">Retirer</flux:button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4">Aucun pays enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $countries->links() }}
    </section>
</x-layouts::app>
