<x-layouts::app :title="__('Historique des quotas')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl">Historique des quotas — {{ $owner->name }}</flux:heading>
        @forelse ($history as $entry)
            <div class="rounded-lg border p-4"><flux:text>{{ $entry->created_at?->format('d/m/Y H:i') }} — {{ $entry->description }}</flux:text><flux:text>Par {{ $entry->causer?->name ?? 'Système' }}</flux:text>
                @if (data_get($entry->properties, 'before.status') !== null)
                    <flux:text>État : {{ \App\Enums\Central\Tenants\StatusEnum::tryFrom((int) data_get($entry->properties, 'before.status'))?->label() ?? 'Inconnu' }} → {{ \App\Enums\Central\Tenants\StatusEnum::tryFrom((int) data_get($entry->properties, 'after.status'))?->label() ?? 'Inconnu' }}</flux:text>
                @endif
                @if (data_get($entry->properties, 'reason'))<flux:text>Motif : {{ data_get($entry->properties, 'reason') }}</flux:text>@endif
            </div>
        @empty <flux:text>Aucune décision enregistrée.</flux:text> @endforelse
        {{ $history->links() }}
        @can('viewQuota', $owner)<flux:button :href="route('central.quotas.show', $owner)">Retour aux quotas</flux:button>@endcan
    </section>
</x-layouts::app>
