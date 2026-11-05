<x-layouts::app :title="__('Historique des abonnements')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Historique des abonnements</flux:heading>
        <flux:text>Commerçant : {{ $subscription->owner?->name ?? 'Compte indisponible' }}</flux:text>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr>@foreach (['Offre', 'État', 'Période', 'Montant convenu', 'Fin', 'Actions'] as $heading)<th class="p-4">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($history as $entry)
                        <tr>
                            <td class="p-4">{{ $entry->plan?->name ?? 'Offre indisponible' }}@if ($entry->plan) — v{{ $entry->plan->version }}@endif</td>
                            <td class="p-4">{{ $entry->status?->label() ?? 'Non renseigné' }}</td>
                            <td class="p-4">{{ $entry->period_starts_at?->format('d/m/Y') }} — {{ $entry->period_ends_at?->format('d/m/Y') ?? 'Sans échéance' }}</td>
                            <td class="p-4">{{ $entry->agreed_amount !== null ? $entry->agreed_amount.' DZD' : 'Non renseigné' }}</td>
                            <td class="p-4">{{ $entry->ended_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="p-4">@can('view', $entry)<flux:button :href="route('central.subscriptions.show', $entry)">Détails</flux:button>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4">Aucun abonnement trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $history->links() }}
        @can('view', $subscription)<flux:button :href="route('central.subscriptions.show', $subscription)">Retour à l’abonnement</flux:button>@endcan
    </section>
</x-layouts::app>
