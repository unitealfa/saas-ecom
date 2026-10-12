<x-layouts::app :title="__('Historique administratif')">
    <section class="mx-auto flex max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">Historique de {{ $user->name }}</flux:heading>
        @forelse ($history as $entry)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading level="2">{{ $entry->description }}</flux:heading>
                <flux:text>{{ $entry->created_at?->format('d/m/Y H:i') }} — {{ $entry->causer?->name ?? 'Système ou compte archivé' }}</flux:text>
                @if ($entry->event === 'central_legal_rejected' || $entry->event === 'central_legal_validated')
                    @can('viewLegal', $user)
                        <flux:text>Version : {{ $entry->getProperty('legal_profile_version') }}</flux:text>
                        @if ($entry->getProperty('reason'))<flux:text>{{ $entry->getProperty('reason') }}</flux:text>@endif
                    @endcan
                @endif
            </div>
        @empty
            <flux:text>Aucune activité administrative enregistrée.</flux:text>
        @endforelse
        {{ $history->links() }}
        <flux:button :href="route('central.users.show', $user)">Retour à la fiche</flux:button>
    </section>
</x-layouts::app>
