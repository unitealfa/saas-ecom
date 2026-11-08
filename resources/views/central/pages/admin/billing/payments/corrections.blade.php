<x-layouts::app title="Signalements de paiements SaaS">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-5">
        <flux:heading size="xl" level="1">Signalements de paiements SaaS</flux:heading>
        <flux:callout>
            Liste des demandes soumises par les commerçants. Un signalement n'est ni un remboursement,
            ni un avoir, ni une validation. Examinez le paiement et son historique avant toute décision.
        </flux:callout>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Date</th><th class="p-3">Paiement</th><th class="p-3">Motif</th><th class="p-3">Action</th></tr></thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $report->created_at->format('d/m/Y H:i') }}</td>
                            <td class="p-3">PAY-{{ $report->properties['payment_uuid'] ?? 'Inconnu' }}</td>
                            <td class="p-3">{{ $report->properties['reason'] ?? 'Motif manquant' }}</td>
                            <td class="p-3">
                                @if ($report->subject instanceof \App\Models\Central\Billing\SaasPayment)
                                    <a class="underline" href="{{ route('central.payments.show', $report->subject) }}">Examiner le paiement</a>
                                @else
                                    <span>Référence historique à rapprocher</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="4">Aucun signalement reçu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $reports->links() }}
        <flux:button :href="route('central.payments.index')">Tous les paiements</flux:button>
    </section>
</x-layouts::app>
