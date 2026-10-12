<x-layouts::app :title="__('Inscriptions en attente')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Inscriptions en attente d’approbation</flux:heading>
        <flux:text>Approuver autorise la connexion. L’adresse e-mail doit également être vérifiée par son propriétaire.</flux:text>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900">
                    <tr>
                        <th class="p-4">Nom</th>
                        <th class="p-4">E-mail</th>
                        <th class="p-4">Téléphone</th>
                        <th class="p-4">Pays</th>
                        <th class="p-4">Inscription</th>
                        <th class="p-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($users as $user)
                        <tr>
                            <td class="p-4">{{ $user->name }}</td>
                            <td class="p-4">{{ $user->email }}</td>
                            <td class="p-4">{{ $user->phone ?? 'Non renseigné' }}</td>
                            <td class="p-4">{{ $user->country?->name_fr }}</td>
                            <td class="p-4">{{ $user->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="p-4">
                                @can('view', $user)
                                    <flux:button :href="route('central.users.show', $user)">Informations</flux:button>
                                @endcan
                                @include('central.pages.admin.users.lifecycle-actions')
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4">Aucune inscription en attente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
        <flux:button :href="route('central.users.index')">Tous les utilisateurs</flux:button>
    </section>
</x-layouts::app>
