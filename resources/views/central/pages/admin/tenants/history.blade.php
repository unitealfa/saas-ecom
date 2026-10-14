<x-layouts::app :title="__('Historique de la boutique')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Historique de {{ $tenant->shop_name }}</flux:heading>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-4">Date</th><th class="p-4">Auteur</th><th class="p-4">Action</th><th class="p-4">Détails</th></tr></thead>
                <tbody>
                    @forelse ($history as $activity)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-4">{{ $activity->created_at->format('d/m/Y H:i') }}</td>
                            <td class="p-4">{{ $activity->causer?->name ?? 'Système' }}</td>
                            <td class="p-4">{{ $activity->description }}</td>
                            <td class="p-4">{{ json_encode($activity->properties->only(['before', 'after', 'reason']), JSON_UNESCAPED_UNICODE) }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-4" colspan="4">Aucune action administrative enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $history->links() }}
        <flux:button :href="route('central.tenants.show', $tenant)">Retour à la boutique</flux:button>
    </section>
</x-layouts::app>
