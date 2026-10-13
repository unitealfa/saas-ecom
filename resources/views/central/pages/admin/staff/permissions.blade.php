<x-layouts::app title="Permissions effectives">
    <section class="mx-auto flex max-w-4xl flex-col gap-6">
        <flux:heading size="xl" level="1">Permissions de {{ $user->name }}</flux:heading>
        <flux:text>Les durées commencent à la date d’attribution du rôle. Une attribution expirée reste visible ici, mais n’autorise plus l’action.</flux:text>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Action</th><th class="p-3">Source</th><th class="p-3">Début</th><th class="p-3">Fin</th><th class="p-3">Accès actuel</th></tr></thead>
                <tbody>
                    @forelse ($permissions as $permission)
                        @foreach ($permission['sources'] as $source)
                            <tr>
                                <td class="p-3">{{ $permission['label'] }}<br>{{ $permission['code'] }}</td>
                                <td class="p-3">{{ $source['label'] }}</td>
                                <td class="p-3">{{ $source['assigned_at']->format('d/m/Y H:i') }}</td>
                                <td class="p-3">{{ $source['expires_at']?->format('d/m/Y H:i') ?? 'Rôle privilégié protégé' }}</td>
                                <td class="p-3">{{ $permission['effective'] ? 'Autorisé' : 'Non autorisé' }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="5" class="p-3">Aucune permission attribuée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <flux:button :href="route('central.staff.show', $user)">Retour à la fiche</flux:button>
    </section>
</x-layouts::app>
